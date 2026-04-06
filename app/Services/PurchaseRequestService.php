<?php

namespace App\Services;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestService
{
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
        if (!$pr) return null;

        $pr->update($data);
        return $pr->fresh(['buyer', 'category']);
    }

    public function delete(int $id): bool
    {
        $pr = PurchaseRequest::find($id);
        return $pr ? $pr->delete() : false;
    }

    public function approve(int $id, int $approverId): ?PurchaseRequest
    {
        $pr = PurchaseRequest::find($id);
        if (!$pr || $pr->status !== PurchaseRequestStatus::SUBMITTED) return null;

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

        return $pr->fresh(['buyer', 'category']);
    }
}
