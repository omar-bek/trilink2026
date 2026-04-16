<?php

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(private readonly RfqService $rfqService) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return PurchaseRequest::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['buyer_id'] ?? null, fn ($q, $v) => $q->where('buyer_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->with(['buyer', 'category', 'subCategory'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?PurchaseRequest
    {
        return PurchaseRequest::with(['buyer', 'company', 'category', 'subCategory', 'rfqs'])->find($id);
    }

    public function create(array $data): PurchaseRequest
    {
        return PurchaseRequest::create($data)->load(['buyer', 'category']);
    }

    public function update(int $id, array $data): ?PurchaseRequest
    {
        $pr = PurchaseRequest::find($id);
        if (! $pr) {
            return null;
        }

        $pr->update($data);

        return $pr->fresh(['buyer', 'category']);
    }

    public function delete(int $id): bool
    {
        $pr = PurchaseRequest::find($id);

        return $pr ? $pr->delete() : false;
    }

    /**
     * Approve a purchase request and auto-generate the corresponding RFQ.
     *
     * Accepts both SUBMITTED and PENDING_APPROVAL statuses because the web
     * controller skips the draft step and creates PRs directly in
     * PENDING_APPROVAL state, while older API callers may still use SUBMITTED.
     */
    public function approve(int $id, int $approverId): ?PurchaseRequest
    {
        $pr = PurchaseRequest::find($id);
        if (! $pr) {
            return null;
        }

        $approvable = [
            PurchaseRequestStatus::SUBMITTED,
            PurchaseRequestStatus::PENDING_APPROVAL,
        ];

        if (! in_array($pr->status, $approvable, true)) {
            return null;
        }

        return DB::transaction(function () use ($pr, $approverId) {
            $history = $pr->approval_history ?? [];
            $history[] = [
                'user_id' => $approverId,
                'action' => 'approved',
                'at' => now()->toISOString(),
            ];

            $pr->update([
                'status' => PurchaseRequestStatus::APPROVED,
                'approval_history' => $history,
            ]);

            // Auto-generate the RFQ from the approved PR. We only do this once
            // per PR — if an RFQ already exists, leave it alone (idempotent).
            if (! $pr->rfq_generated) {
                $this->rfqService->create($this->buildRfqDataFromPr($pr));
            }

            return $pr->fresh(['buyer', 'category', 'rfqs']);
        });
    }

    /**
     * Reject a purchase request with an optional reason recorded in history.
     */
    public function reject(int $id, int $approverId, ?string $reason = null): ?PurchaseRequest
    {
        $pr = PurchaseRequest::find($id);
        if (! $pr) {
            return null;
        }

        $rejectable = [
            PurchaseRequestStatus::SUBMITTED,
            PurchaseRequestStatus::PENDING_APPROVAL,
        ];

        if (! in_array($pr->status, $rejectable, true)) {
            return null;
        }

        $history = $pr->approval_history ?? [];
        $history[] = [
            'user_id' => $approverId,
            'action' => 'rejected',
            'reason' => $reason,
            'at' => now()->toISOString(),
        ];

        $pr->update([
            'status' => PurchaseRequestStatus::REJECTED,
            'approval_history' => $history,
        ]);

        return $pr->fresh(['buyer', 'category']);
    }

    /**
     * Build the RFQ payload from an approved PR. Default type is SUPPLIER
     * (materials sourcing) — logistics/clearance RFQs are added separately
     * via the additional services flow.
     */
    private function buildRfqDataFromPr(PurchaseRequest $pr): array
    {
        return [
            'title' => $pr->title,
            'description' => $pr->description,
            'company_id' => $pr->company_id,
            'branch_id' => $pr->branch_id,
            'purchase_request_id' => $pr->id,
            'type' => RfqType::SUPPLIER->value,
            'status' => RfqStatus::OPEN->value,
            'items' => $pr->items ?? [],
            'budget' => $pr->budget,
            'currency' => $pr->currency,
            'deadline' => $pr->required_date,
            'delivery_location' => is_array($pr->delivery_location)
                ? json_encode($pr->delivery_location)
                : $pr->delivery_location,
            'is_anonymous' => false,
            'category_id' => $pr->category_id,
        ];
    }
}
