<?php

namespace App\Services;

use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\LosingBidNotification;
use App\Notifications\RfqCancelledNotification;
use App\Notifications\RfqClosedNotification;
use App\Notifications\RfqPublishedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class RfqService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Rfq::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['target_role'] ?? null, fn ($q, $v) => $q->where('target_role', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->with(['company', 'category'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Rfq
    {
        return Rfq::with(['company', 'purchaseRequest', 'category', 'bids.company'])->find($id);
    }

    public function create(array $data): Rfq
    {
        $rfq = Rfq::create($data);

        if ($rfq->purchase_request_id) {
            PurchaseRequest::where('id', $rfq->purchase_request_id)
                ->update(['rfq_generated' => true]);
        }

        $rfq->load(['company', 'category']);

        // Auto-publish — RFQs are created in OPEN status by the PR
        // approval flow (see PurchaseRequestService::buildRfqDataFromPr).
        // Anything that's already OPEN at create time should fan out to
        // matching suppliers immediately.
        if ($rfq->status === RfqStatus::OPEN->value || $rfq->status === RfqStatus::OPEN) {
            $this->notifyMatchingSuppliers($rfq);
        }

        return $rfq;
    }

    public function update(int $id, array $data): ?Rfq
    {
        $rfq = Rfq::find($id);
        if (!$rfq) return null;

        // Capture the previous status so we can detect a draft → open
        // transition (manual publish) and fan out the notification then.
        $wasOpen = $rfq->status === RfqStatus::OPEN || $rfq->status === RfqStatus::OPEN->value;

        $rfq->update($data);
        $rfq = $rfq->fresh(['company', 'category']);

        $isOpen = $rfq && ($rfq->status === RfqStatus::OPEN || $rfq->status === RfqStatus::OPEN->value);
        if ($rfq && !$wasOpen && $isOpen) {
            $this->notifyMatchingSuppliers($rfq);
        }

        return $rfq;
    }

    public function delete(int $id): bool
    {
        $rfq = Rfq::find($id);
        return $rfq ? $rfq->delete() : false;
    }

    public function getByPurchaseRequest(int $purchaseRequestId): LengthAwarePaginator
    {
        return Rfq::where('purchase_request_id', $purchaseRequestId)
            ->with(['company', 'bids'])
            ->latest()
            ->paginate(15);
    }

    /**
     * Mark an RFQ as cancelled and notify every supplier that bid on
     * it. Different from delete — cancellation keeps the row for
     * audit and analytics, it just stops the procurement.
     */
    public function cancel(int $id, ?string $reason = null): ?Rfq
    {
        $rfq = Rfq::with(['bids.provider'])->find($id);
        if (!$rfq) {
            return null;
        }

        $rfq->update(['status' => RfqStatus::CLOSED->value]);

        $bidders = $rfq->bids->pluck('provider')->filter()->unique('id');
        if ($bidders->isNotEmpty()) {
            Notification::send($bidders, new RfqCancelledNotification($rfq, $reason));
        }

        return $rfq->fresh(['company', 'category']);
    }

    /**
     * Mark the RFQ as closed (deadline reached, stop accepting bids)
     * and notify every supplier that participated. The actual award
     * decision goes through BidController::accept which fires
     * BidAccepted + LosingBid as a separate event chain.
     */
    public function close(int $id): ?Rfq
    {
        $rfq = Rfq::with(['bids.provider'])->find($id);
        if (!$rfq) {
            return null;
        }

        $rfq->update(['status' => RfqStatus::CLOSED->value]);

        $bidders = $rfq->bids->pluck('provider')->filter()->unique('id');
        if ($bidders->isNotEmpty()) {
            Notification::send($bidders, new RfqClosedNotification($rfq));
        }

        return $rfq->fresh(['company', 'category']);
    }

    /**
     * Fan out a "losing bid" notice to every supplier that bid on the
     * RFQ except the winner. Called from BidController::accept after
     * the winning bid is marked accepted — gives losing suppliers the
     * dignity of an explicit award decision instead of a silent reject.
     */
    public function notifyLosingBidders(Rfq $rfq, int $winningBidId): void
    {
        $rfq->loadMissing('bids.provider');
        $losers = $rfq->bids
            ->where('id', '!=', $winningBidId)
            ->map(fn ($bid) => ['user' => $bid->provider, 'bid' => $bid])
            ->filter(fn ($row) => $row['user'] !== null);

        foreach ($losers as $row) {
            $row['user']->notify(new LosingBidNotification($row['bid']));
        }
    }

    /**
     * Resolve every active user belonging to a supplier company whose
     * category set intersects with the RFQ's category, then dispatch
     * the published notification. The buyer's own company is excluded
     * so the team that posted the RFQ doesn't get a "new RFQ in your
     * category" alert about their own work.
     *
     * Capped at 500 recipients per RFQ to keep a runaway broadcast
     * from saturating the queue if a category has thousands of suppliers
     * — the rest still see it through the marketplace listing.
     */
    private function notifyMatchingSuppliers(Rfq $rfq): void
    {
        if (!$rfq->category_id) {
            return;
        }

        $companyIds = Company::query()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $rfq->category_id))
            ->where('id', '!=', $rfq->company_id)
            ->pluck('id');

        if ($companyIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('email')
            ->limit(500)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new RfqPublishedNotification($rfq));
        }
    }
}
