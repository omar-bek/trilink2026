<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

trait FormatsForViews
{
    /**
     * Per-request cache for supplier-side detection so the company lookup
     * fires at most once per controller action even when multiple call
     * sites ask the same question.
     *
     * @var array<int, bool>
     */
    private array $supplierSideCache = [];

    /**
     * Format an amount with currency, e.g. "AED 95,000".
     */
    protected function money(?float $amount, string $currency = 'AED'): string
    {
        return $currency . ' ' . number_format((float) $amount);
    }

    /**
     * Phase 3 / Sprint 14 / task 3.14 — money formatter that converts
     * across currencies. When `$displayCurrency` matches the source the
     * output is identical to money(); otherwise it tags the converted
     * value with the converted currency code AND the original-currency
     * note in parentheses, e.g. "USD 25,887 (AED 95,000)".
     *
     * Always returns a usable string — when no exchange rate exists, the
     * helper degrades gracefully to the source amount unchanged.
     */
    protected function moneyConverted(?float $amount, string $sourceCurrency = 'AED', ?string $displayCurrency = null): string
    {
        $sourceCurrency  = strtoupper($sourceCurrency);
        $displayCurrency = strtoupper($displayCurrency ?? $sourceCurrency);

        if ($displayCurrency === $sourceCurrency) {
            return $this->money($amount, $sourceCurrency);
        }

        $converted = ExchangeRate::convert((float) $amount, $sourceCurrency, $displayCurrency);

        return sprintf(
            '%s (%s)',
            $this->money($converted, $displayCurrency),
            $this->money($amount, $sourceCurrency),
        );
    }

    /**
     * Format a date as "Mar 15, 2026".
     */
    protected function date($date): string
    {
        return $date ? Carbon::parse($date)->format('M j, Y') : '';
    }

    /**
     * Format a date as "April 18, 2026".
     */
    protected function longDate($date): string
    {
        return $date ? Carbon::parse($date)->format('F j, Y') : '';
    }

    /**
     * Map an enum value (or string) to its scalar value.
     */
    protected function statusValue($status): string
    {
        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) $status;
    }

    /**
     * Resolve the current user's company id (or fall back to first buyer company).
     */
    protected function currentCompanyId(): ?int
    {
        $user = auth()->user();

        return $user?->company_id;
    }

    /**
     * Pure-supplier-side user roles. These roles will always land on the
     * supplier-facing views regardless of company type. Cross-cutting
     * roles (company_manager, finance, sales, branch_manager …) are NOT
     * in this list — for them we look at the company TYPE instead, so a
     * "Company Manager" of a supplier company sees the supplier views
     * and a "Company Manager" of a buyer company sees the buyer views.
     */
    private const SUPPLIER_SIDE_ROLES = [
        'supplier',
        'service_provider',
        'logistics',
        'clearance',
    ];

    /**
     * Pure-supplier-side company types. Any user attached to a company
     * with one of these types is treated as supplier-side, regardless of
     * what their personal role is.
     */
    private const SUPPLIER_SIDE_COMPANY_TYPES = [
        'supplier',
        'service_provider',
        'logistics',
        'clearance',
    ];

    /**
     * Whether the current authenticated user should see the supplier
     * variants of role-aware views (My Contracts, Marketplace RFQs,
     * Submitted Bids …) instead of the buyer variants.
     *
     * Two routing inputs are considered:
     *
     *   1. The user's ROLE — pure supplier-side roles (supplier,
     *      service_provider, logistics, clearance) always count as
     *      supplier-side.
     *   2. The user's COMPANY TYPE — for cross-cutting roles such as
     *      company_manager / finance / sales / branch_manager, the
     *      company type is the source of truth. A company manager of a
     *      supplier company is supplier-side; a company manager of a
     *      buyer company is buyer-side.
     *
     * The previous role-only check missed (2), which made every
     * company_manager (and every other cross-cutting role) of a
     * supplier company silently land on the buyer index — empty,
     * because the buyer query filters by buyer_company_id and the
     * supplier company never matches it.
     */
    protected function isSupplierSideUser(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $cacheKey = (int) $user->id;
        if (array_key_exists($cacheKey, $this->supplierSideCache)) {
            return $this->supplierSideCache[$cacheKey];
        }

        // Step 1 — pure supplier-side role wins immediately.
        $role = $user->role?->value ?? null;
        if ($role !== null && in_array($role, self::SUPPLIER_SIDE_ROLES, true)) {
            return $this->supplierSideCache[$cacheKey] = true;
        }

        // Step 2 — fall back to company type. Only the type column is
        // selected so this stays a single tiny query and the per-request
        // cache makes repeat calls free.
        if (!$user->company_id) {
            return $this->supplierSideCache[$cacheKey] = false;
        }

        $type = Company::query()
            ->whereKey($user->company_id)
            ->value('type');

        $typeValue = $type instanceof CompanyType ? $type->value : (string) $type;

        return $this->supplierSideCache[$cacheKey] = in_array(
            $typeValue,
            self::SUPPLIER_SIDE_COMPANY_TYPES,
            true,
        );
    }
}
