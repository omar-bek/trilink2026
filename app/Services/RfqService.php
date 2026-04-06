<?php

namespace App\Services;

use App\Enums\RfqStatus;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

        return $rfq->load(['company', 'category']);
    }

    public function update(int $id, array $data): ?Rfq
    {
        $rfq = Rfq::find($id);
        if (!$rfq) return null;

        $rfq->update($data);
        return $rfq->fresh(['company', 'category']);
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
}
