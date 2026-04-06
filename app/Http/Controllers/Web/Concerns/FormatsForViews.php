<?php

namespace App\Http\Controllers\Web\Concerns;

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
