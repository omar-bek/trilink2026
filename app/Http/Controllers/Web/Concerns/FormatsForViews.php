<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

trait FormatsForViews
{
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
}
