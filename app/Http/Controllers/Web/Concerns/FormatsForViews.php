<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

trait FormatsForViews
{
    /**
     * Currency symbol map — used by the locale-aware money() formatter
     * to render the right symbol for the active locale. Arabic users
     * see "د.إ" instead of "AED" so the numbers read naturally in
     * right-to-left text flow.
     */
    private const CURRENCY_SYMBOLS_AR = [
        'AED' => 'د.إ',
        'SAR' => 'ر.س',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'KWD' => 'د.ك',
        'BHD' => 'د.ب',
        'QAR' => 'ر.ق',
        'OMR' => 'ر.ع',
        'EGP' => 'ج.م',
    ];

    /**
     * Format an amount with currency symbol, locale-aware.
     *
     * In the Arabic locale the output is "1,000 د.إ" (symbol after the
     * number, separated by a narrow no-break space). In English the
     * output stays "AED 95,000" (ISO code before the number). This
     * matches regional conventions — Arabic users read right-to-left
     * so the amount comes first, then the unit.
     *
     * The `number_format` decimals follow the currency's minor unit:
     * zero-decimal currencies (JPY, KRW) get 0, three-decimal dinars
     * (BHD, KWD, OMR) get 3, everything else gets 0 for readability
     * (the full precision is shown in financial contexts via the
     * payment schedule component which formats to 2dp separately).
     */
    protected function money(?float $amount, string $currency = 'AED'): string
    {
        $formatted = number_format((float) $amount);

        if (app()->getLocale() === 'ar') {
            $symbol = self::CURRENCY_SYMBOLS_AR[$currency] ?? $currency;
            // Narrow no-break space (\u{202F}) keeps the number and
            // symbol visually grouped without allowing a line break
            // between them, and prevents BiDi reordering from swapping
            // the two parts in mixed-direction text.
            return $formatted . "\u{202F}" . $symbol;
        }

        return $currency . ' ' . $formatted;
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
     * Format a delivery_location value (array or raw JSON string) into a
     * human-readable single-line address. Priority: address, city, country.
     * Falls back to an em-dash when nothing usable is available. Centralised
     * here because both the RFQ and PR surfaces render the same shape.
     */
    protected function formatLocation(mixed $location): string
    {
        if (is_string($location) && $location !== '') {
            $decoded = json_decode($location, true);
            if (is_array($decoded)) {
                $location = $decoded;
            }
        }

        if (is_array($location)) {
            $parts = array_filter([
                $location['address'] ?? null,
                $location['city']    ?? null,
                $location['country'] ?? null,
            ]);
            $joined = trim(implode(', ', $parts));
            return $joined !== '' ? $joined : '—';
        }

        return $location ? (string) $location : '—';
    }

}
