<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Category::query()
            ->when($filters['parent_id'] ?? null, fn ($q, $v) => $q->where('parent_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                    ->orWhere('name_ar', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function tree(): Collection
    {
        return Category::whereNull('parent_id')
            ->where('is_active', true)
            ->with('allChildren')
            ->get();
    }

    public function find(int $id): ?Category
    {
        return Category::with(['parent', 'children'])->find($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(int $id, array $data): ?Category
    {
        $category = Category::find($id);
        if (!$category) return null;

        $category->update($data);
        return $category->fresh(['parent', 'children']);
    }

    public function delete(int $id): bool
    {
        $category = Category::find($id);
        return $category ? $category->delete() : false;
    }
}
