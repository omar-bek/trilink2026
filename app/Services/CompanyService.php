<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Company::query()
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                    ->orWhere('registration_number', 'like', "%{$v}%");
            }))
            ->withCount('users')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Company
    {
        return Company::with(['users', 'categories'])->find($id);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(int $id, array $data): ?Company
    {
        $company = Company::find($id);
        if (! $company) {
            return null;
        }

        $company->update($data);

        return $company->fresh();
    }

    public function delete(int $id): bool
    {
        $company = Company::find($id);

        return $company ? $company->delete() : false;
    }

    public function linkCategories(int $companyId, array $categoryIds): void
    {
        $company = Company::findOrFail($companyId);
        $company->categories()->sync($categoryIds);
    }
}
