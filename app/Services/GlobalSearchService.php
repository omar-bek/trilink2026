<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\Rfq;
use Illuminate\Support\Collection;

/**
 * Cross-resource search — Phase 1 / task 1.12.
 *
 * Backed by the App\Concerns\Searchable trait that already lives on each
 * model (added in Phase 0 / task 0.3). This service is the single fan-out
 * point: one query string in, three keyed result collections out.
 *
 * Why a service instead of a controller method?
 *   - The same search runs from the global search bar in the topbar AND
 *     the dedicated /dashboard/search page. Centralising it means there's
 *     one place to plug in Meilisearch or pgvector later.
 */
class GlobalSearchService
{
    /** @var int Maximum results returned per resource. */
    public const PER_RESOURCE_LIMIT = 20;

    /**
     * Run a federated search across the three discoverable resources.
     *
     * @return array{rfqs:Collection<int,Rfq>,products:Collection<int,Product>,suppliers:Collection<int,Company>,total:int}
     */
    public function search(string $term, ?int $viewerCompanyId = null): array
    {
        $term = trim($term);
        if ($term === '') {
            return [
                'rfqs' => collect(),
                'products' => collect(),
                'suppliers' => collect(),
                'total' => 0,
            ];
        }

        // RFQs — only OPEN ones from companies other than the viewer's.
        $rfqs = Rfq::query()
            ->where('status', RfqStatus::OPEN->value)
            ->when($viewerCompanyId, fn ($q) => $q->where('company_id', '!=', $viewerCompanyId))
            ->search($term, ['title', 'rfq_number'])
            ->with(['company:id,name', 'category:id,name'])
            ->latest()
            ->limit(self::PER_RESOURCE_LIMIT)
            ->get();

        // Products — active catalog entries.
        $products = Product::query()
            ->where('is_active', true)
            ->search($term, ['name', 'description', 'sku'])
            ->with(['company:id,name', 'category:id,name'])
            ->latest()
            ->limit(self::PER_RESOURCE_LIMIT)
            ->get();

        // Suppliers — active supplier-type companies (excluding viewer).
        $suppliers = Company::query()
            ->whereIn('type', [CompanyType::SUPPLIER->value, CompanyType::SERVICE_PROVIDER->value])
            ->where('status', CompanyStatus::ACTIVE->value)
            ->when($viewerCompanyId, fn ($q) => $q->where('id', '!=', $viewerCompanyId))
            ->search($term, ['name', 'name_ar', 'description'])
            ->limit(self::PER_RESOURCE_LIMIT)
            ->get(['id', 'name', 'name_ar', 'country', 'verification_level']);

        return [
            'rfqs' => $rfqs,
            'products' => $products,
            'suppliers' => $suppliers,
            'total' => $rfqs->count() + $products->count() + $suppliers->count(),
        ];
    }
}
