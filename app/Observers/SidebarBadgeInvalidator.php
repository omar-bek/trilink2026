<?php

namespace App\Observers;

use App\Models\Bid;
use App\Services\SidebarBadgeService;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic cache-invalidator that clears the per-company SidebarBadgeService
 * cache whenever a model that affects a badge count is saved or deleted.
 *
 * Wired in App\Providers\AppServiceProvider against the small set of
 * "badge-affecting" models (PurchaseRequest, Rfq, Bid, Contract, Payment,
 * Shipment, Dispute, EscrowAccount). The observer is intentionally
 * model-agnostic — it inspects the model for one of the well-known
 * company foreign keys and forgets that company's cache.
 *
 * Why this exists: previously the SidebarBadgeService cached counts for
 * 60s but had no listener wiring, so a freshly approved purchase request
 * could take up to a minute to appear in the sidebar. With this observer
 * the cache is invalidated the instant the underlying row changes.
 */
class SidebarBadgeInvalidator
{
    public function __construct(
        private readonly SidebarBadgeService $service,
    ) {}

    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        // Try the standard FK columns in order. The first one that
        // exists on the model wins. Two-sided models (Bid has both
        // company_id for the supplier and rfq.company_id for the buyer)
        // get both sides invalidated.
        $companyIds = [];

        foreach (['company_id', 'buyer_company_id', 'recipient_company_id', 'against_company_id', 'logistics_company_id'] as $column) {
            if (isset($model->{$column}) && $model->{$column}) {
                $companyIds[] = (int) $model->{$column};
            }
        }

        // Special case: a Bid affects both the supplier company and the
        // RFQ-owning buyer company. Pull the parent RFQ if it's loaded.
        if ($model instanceof Bid && $model->rfq) {
            $companyIds[] = (int) $model->rfq->company_id;
        }

        foreach (array_unique(array_filter($companyIds)) as $cid) {
            $this->service->forgetForCompany($cid);
        }
    }
}
