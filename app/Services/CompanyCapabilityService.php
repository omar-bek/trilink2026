<?php

namespace App\Services;

use App\Models\Company;

/**
 * Capability resolver for the dual-role model: every company can both
 * buy and sell. Whether a company is "buyer-capable" or "supplier-
 * capable" is decided by what it actually has — assigned categories
 * for the supplier side, and the existence of at least one buyer-side
 * employee for the buyer side. The legacy companies.type column is
 * intentionally NOT consulted; it is now a free-text business
 * descriptor that the manager can edit.
 *
 * Use these helpers in any place that needs to gate a feature by
 * "can this company supply / buy?" — never reach for company.type.
 */
class CompanyCapabilityService
{
    /**
     * A company can SELL if it has at least one admin-approved category.
     * Categories are how the supplier marketplace and buyer search
     * surface a company's offerings, so "no category" = "nothing to
     * offer". The category-request feature on the profile page is the
     * managed path to gain this capability.
     */
    public function canSupply(Company $company): bool
    {
        return $company->categories()->exists();
    }

    /**
     * A company can BUY by default — buying does not require any
     * preconditions in this platform. The presence of at least one
     * non-supplier-only employee is enough; if the company has any
     * users at all, it can post purchase requests and RFQs.
     */
    public function canBuy(Company $company): bool
    {
        return $company->users()->exists();
    }
}
