<?php

namespace App\Services\AI;

use App\Models\Bid;
use App\Models\Category;
use App\Models\Contract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 / Sprint C — predictive analytics. Looks at the platform's
 * historical bid + contract data and projects:
 *
 *   - "Average price per unit" trend for a category over the past 6 months
 *   - Lead-time forecast for the supplier-buyer pair
 *   - Win probability for a given bid (suppliers care about this)
 *
 * Deliberately rule-based / statistical, not Claude. Predictions over
 * tabular data are exactly what regressions are good at, and we'd waste
 * tokens asking Claude to do arithmetic. Falls back to coarse defaults
 * when the dataset is too small.
 */
class PredictiveAnalyticsService
{
    /**
     * Average bid price for a category over the last N days. Returns
     * null when there's not enough data to be meaningful.
     */
    public function averagePriceForCategory(int $categoryId, int $days = 180): ?array
    {
        $cacheKey = "pred:avg_price:{$categoryId}:{$days}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($categoryId, $days) {
            $rows = Bid::query()
                ->whereHas('rfq', fn ($q) => $q->where('category_id', $categoryId))
                ->where('created_at', '>=', now()->subDays($days))
                ->get(['price', 'currency', 'created_at']);

            if ($rows->count() < 5) {
                return null;
            }

            $byCurrency = $rows->groupBy('currency')->map(fn ($items) => [
                'count' => $items->count(),
                'avg' => round($items->avg('price'), 2),
                'min' => round($items->min('price'), 2),
                'max' => round($items->max('price'), 2),
            ]);

            return [
                'category_id' => $categoryId,
                'window_days' => $days,
                'sample_size' => $rows->count(),
                'by_currency' => $byCurrency,
            ];
        });
    }

    /**
     * Estimated lead time (days) for a supplier company. Average over
     * their last 20 contracts. Falls back to the supplier's average bid
     * lead-time when no contracts exist.
     */
    public function leadTimeForecast(int $supplierCompanyId): ?array
    {
        $cacheKey = "pred:lead:{$supplierCompanyId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($supplierCompanyId) {
            $contracts = Contract::query()
                ->whereJsonContains('parties', ['company_id' => $supplierCompanyId])
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->latest()
                ->limit(20)
                ->get(['start_date', 'end_date']);

            if ($contracts->count() >= 3) {
                $diffs = $contracts->map(fn ($c) => (int) $c->start_date->diffInDays($c->end_date));

                return [
                    'sample_size' => $contracts->count(),
                    'avg_days' => (int) round($diffs->avg()),
                    'min_days' => (int) $diffs->min(),
                    'max_days' => (int) $diffs->max(),
                    'source' => 'contracts',
                ];
            }

            // Fallback — supplier's average bid delivery_time_days.
            $avgBidLead = (float) Bid::query()
                ->where('company_id', $supplierCompanyId)
                ->whereNotNull('delivery_time_days')
                ->avg('delivery_time_days');

            if ($avgBidLead > 0) {
                return [
                    'sample_size' => 0,
                    'avg_days' => (int) round($avgBidLead),
                    'min_days' => (int) round($avgBidLead * 0.7),
                    'max_days' => (int) round($avgBidLead * 1.3),
                    'source' => 'bids',
                ];
            }

            return null;
        });
    }

    /**
     * Win probability for an open bid. Heuristic:
     *   1. Rank the bid by price among competitors (lowest = best).
     *   2. Boost / penalise by supplier's historical accept-rate.
     *   3. Clamp to [0.05, 0.95].
     */
    public function winProbability(Bid $bid): float
    {
        $bid->loadMissing('rfq.bids');
        $rfq = $bid->rfq;
        if (! $rfq || $rfq->bids->isEmpty()) {
            return 0.5;
        }

        $sorted = $rfq->bids->sortBy('price')->values();
        $rank = $sorted->search(fn ($b) => $b->id === $bid->id);
        if ($rank === false) {
            return 0.5;
        }

        $count = $sorted->count();
        // Lowest price gets ~0.8, then decay linearly.
        $base = max(0.1, 0.8 - ($rank * (0.6 / max(1, $count - 1))));

        // Adjust by supplier's historical accept-rate. Pulled from the
        // bids table directly to avoid an extra service dependency.
        $supplierAcceptRate = (float) (DB::table('bids')
            ->selectRaw('AVG(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rate', ['accepted'])
            ->where('company_id', $bid->company_id)
            ->value('rate') ?? 0.5);

        $adjusted = ($base * 0.7) + ($supplierAcceptRate * 0.3);

        return round(max(0.05, min(0.95, $adjusted)), 2);
    }
}
