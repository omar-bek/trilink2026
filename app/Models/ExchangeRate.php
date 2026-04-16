<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Daily currency exchange rate cache. Sprint 14 / task 3.13 — populated by
 * the `fx:sync` command from Open Exchange Rates (or any other provider
 * we plug in via config later).
 *
 * Schema is keyed by (from, to, as_of) so we can replay historical
 * conversions for audit purposes (e.g. when a 2025 contract was signed
 * the rate was X — let's not retroactively re-price it at today's rate).
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'as_of',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'as_of' => 'date',
        ];
    }

    /**
     * Convert an amount between two currencies using the most recent rate
     * we have on or before the given as-of date. Returns the amount
     * unchanged when from === to. Falls back to 1.0 (no conversion) when
     * no rate exists at all so callers degrade gracefully on a fresh DB.
     *
     * Cached for 5 minutes per (from, to, as_of) so a contract list page
     * with 50 rows in 3 different currencies doesn't fire 50 lookups.
     */
    public static function convert(float $amount, string $from, string $to, ?Carbon $asOf = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rate = self::lookupRate($from, $to, $asOf);

        return round($amount * $rate, 2);
    }

    /**
     * Resolve the rate to apply for a (from, to) pair on a given date.
     * Strategy:
     *   1. Direct row in the table for this pair (most recent on/before
     *      the as-of date).
     *   2. Inverse row (to → from) — divide 1 by it.
     *   3. Triangulate via USD when neither leg exists directly.
     *   4. Fallback 1.0 (no conversion) when nothing helps.
     */
    public static function lookupRate(string $from, string $to, ?Carbon $asOf = null): float
    {
        $asOf = ($asOf ?? Carbon::today())->toDateString();
        $key = "fx:{$from}:{$to}:{$asOf}";

        return (float) Cache::remember($key, 300, function () use ($from, $to, $asOf) {
            // Direct.
            $direct = self::query()
                ->where('from_currency', $from)
                ->where('to_currency', $to)
                ->where('as_of', '<=', $asOf)
                ->orderByDesc('as_of')
                ->value('rate');
            if ($direct !== null) {
                return (float) $direct;
            }

            // Inverse.
            $inverse = self::query()
                ->where('from_currency', $to)
                ->where('to_currency', $from)
                ->where('as_of', '<=', $asOf)
                ->orderByDesc('as_of')
                ->value('rate');
            if ($inverse !== null && (float) $inverse > 0) {
                return 1.0 / (float) $inverse;
            }

            // Triangulate via USD if neither end-point hits.
            if ($from !== 'USD' && $to !== 'USD') {
                $fromUsd = self::query()
                    ->where('from_currency', $from)
                    ->where('to_currency', 'USD')
                    ->where('as_of', '<=', $asOf)
                    ->orderByDesc('as_of')
                    ->value('rate');
                $toUsd = self::query()
                    ->where('from_currency', 'USD')
                    ->where('to_currency', $to)
                    ->where('as_of', '<=', $asOf)
                    ->orderByDesc('as_of')
                    ->value('rate');
                if ($fromUsd && $toUsd) {
                    return (float) $fromUsd * (float) $toUsd;
                }
            }

            return 1.0;
        });
    }
}
