<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class CategoryRoutingService
{
    public function findMatchingCompanies(int $categoryId, ?string $companyType = null): Collection
    {
        return Company::whereHas('categories', function ($q) use ($categoryId) {
            $category = Category::find($categoryId);
            if (!$category) return;

            $q->where('categories.id', $categoryId);

            if ($category->parent_id) {
                $q->orWhere('categories.id', $category->parent_id);
            }

            $childIds = Category::where('parent_id', $categoryId)->pluck('id');
            if ($childIds->isNotEmpty()) {
                $q->orWhereIn('categories.id', $childIds);
            }
        })
            ->when($companyType, fn ($q, $v) => $q->where('type', $v))
            ->where('status', 'active')
            ->get();
    }

    public function canCompanyViewPurchaseRequest(int $companyId, int $categoryId): bool
    {
        $company = Company::find($companyId);
        if (!$company) return false;

        return $company->categories()
            ->where(function ($q) use ($categoryId) {
                $category = Category::find($categoryId);
                if (!$category) return;

                $q->where('categories.id', $categoryId);

                if ($category->parent_id) {
                    $q->orWhere('categories.id', $category->parent_id);
                }

                $ancestorIds = $this->getAncestorIds($category);
                if (!empty($ancestorIds)) {
                    $q->orWhereIn('categories.id', $ancestorIds);
                }
            })
            ->exists();
    }

    private function getAncestorIds(Category $category): array
    {
        $ids = [];
        $current = $category;

        while ($current->parent_id) {
            $ids[] = $current->parent_id;
            $current = Category::find($current->parent_id);
            if (!$current) break;
        }

        return $ids;
    }
}
