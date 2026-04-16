<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Enums\RfqStatus;
use App\Models\Bid;
use App\Models\Company;
use App\Models\CompanySupplier;
use App\Models\Rfq;
use App\Models\SanctionsScreening;
use App\Models\User;
use App\Notifications\NewBidNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class BidService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Bid::query()
            ->when($filters['rfq_id'] ?? null, fn ($q, $v) => $q->where('rfq_id', $v))
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->with(['rfq', 'company', 'provider'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Bid
    {
        return Bid::with(['rfq.company', 'company', 'provider'])->find($id);
    }

    public function create(array $data): Bid|string
    {
        $rfq = Rfq::find($data['rfq_id']);
        if (!$rfq || $rfq->status !== RfqStatus::OPEN) {
            return 'RFQ is not open for bidding';
        }

        if ($rfq->company_id === $data['company_id']) {
            return 'Cannot bid on your own RFQ';
        }

        // Sanctions block: a company flagged on any watchlist (OFAC, UN, EU,
        // OpenSanctions consolidated) cannot transact on the platform. This
        // applies to both sides — a sanctioned bidder OR a sanctioned RFQ
        // author short-circuits the bid.
        $bidderCompany = Company::find($data['company_id']);
        if ($bidderCompany?->sanctions_status === SanctionsScreening::RESULT_HIT) {
            return 'Your company is currently flagged on a sanctions watchlist. Contact support to resolve.';
        }
        if ($rfq->company?->sanctions_status === SanctionsScreening::RESULT_HIT) {
            return 'The buying company is currently flagged on a sanctions watchlist. Bid blocked.';
        }

        $existingBid = Bid::where('rfq_id', $data['rfq_id'])
            ->where('company_id', $data['company_id'])
            ->exists();

        if ($existingBid) {
            return 'You have already submitted a bid for this RFQ';
        }

        if (isset($data['validity_date']) && now()->gt($data['validity_date'])) {
            return 'Validity date must be in the future';
        }

        $bid = Bid::create($data)->load(['rfq', 'company', 'provider']);

        // Notify every user inside the buyer's company that a new bid arrived.
        // Suppliers don't get notified about their own bid — only the buyer side.
        $buyerUsers = User::where('company_id', $rfq->company_id)->active()->get();
        if ($buyerUsers->isNotEmpty()) {
            Notification::send($buyerUsers, new NewBidNotification($bid));
        }

        return $bid;
    }

    public function update(int $id, array $data): ?Bid
    {
        $bid = Bid::findOrFail($id);

        $bid->update($data);
        return $bid->fresh(['rfq', 'company', 'provider']);
    }

    public function delete(int $id): bool
    {
        return Bid::findOrFail($id)->delete();
    }

    public function evaluate(int $id, array $aiScore): ?Bid
    {
        $bid = Bid::findOrFail($id);

        $bid->update([
            'ai_score' => $aiScore,
            'status' => BidStatus::UNDER_REVIEW,
        ]);

        return $bid->fresh(['rfq', 'company']);
    }

    public function withdraw(int $id): ?Bid
    {
        $bid = Bid::find($id);
        if (!$bid || !in_array($bid->status, [BidStatus::DRAFT, BidStatus::SUBMITTED])) {
            return null;
        }

        $bid->update(['status' => BidStatus::WITHDRAWN]);
        return $bid->fresh();
    }
}
