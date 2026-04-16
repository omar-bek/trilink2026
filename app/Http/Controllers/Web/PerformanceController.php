<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Performance Dashboard — totals + monthly history + quality metrics.
 *
 * Two distinct metric sets:
 * - **Supplier**: bids submitted, bids won, win rate, total revenue.
 *   Monthly chart shows submission/win/revenue.
 * - **Buyer**: PRs created, RFQs published, contracts signed, total spend.
 *   Monthly chart shows spend + activity.
 */
class PerformanceController extends Controller
{
    use FormatsForViews;

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);

        $companyId = $this->currentCompanyId();

        // Per-company activity detection (replaces isSupplierSideUser).
        // A dual-role company sees whichever side has more activity; both
        // sides are valid — the user can navigate to the other via the
        // sidebar. The previous role-only dispatch hid supplier KPIs from
        // cross-cutting roles (company_manager) of dual-role companies.
        $hasSupplierBids = $companyId
            ? Bid::where('company_id', $companyId)->exists()
            : false;
        $hasBuyerRfqs = $companyId
            ? Rfq::where('company_id', $companyId)->exists()
            : false;

        // Prefer the side with activity. When both sides are empty (new
        // account, no data yet) fall back to the user's declared role so a
        // supplier seat still sees supplier KPIs on day one. When both
        // sides have activity, keep buyer as the safer default — contracts
        // + spend are meaningful to any company.
        if ($hasSupplierBids && ! $hasBuyerRfqs) {
            $showSupplier = true;
        } elseif (! $hasSupplierBids && ! $hasBuyerRfqs) {
            $showSupplier = auth()->user()?->role?->value === 'supplier';
        } else {
            $showSupplier = false;
        }

        return $showSupplier
            ? $this->supplierPerformance($companyId)
            : $this->buyerPerformance($companyId);
    }

    /**
     * Supplier metrics: how many bids did I submit, win, and revenue earned.
     */
    private function supplierPerformance(?int $companyId): View
    {
        $totalBids = Bid::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $wonBids = Bid::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', BidStatus::ACCEPTED->value)
            ->count();

        $totalRevenue = Payment::query()
            ->when($companyId, fn ($q) => $q->where('recipient_company_id', $companyId))
            ->where('status', 'completed')
            ->sum('total_amount');

        $winRate = $totalBids > 0 ? round(($wonBids / $totalBids) * 100, 1) : 0;

        // Real month-over-month growth — comparing this month to last month —
        // so the headline trend badges aren't a hardcoded lie.
        $bidsGrowth = $this->growthPercent(Bid::class, $companyId, 'company_id', 'created_at');
        $revenueGrowth = $this->growthPercent(Payment::class, $companyId, 'recipient_company_id', 'created_at', 'total_amount', ['status' => 'completed']);

        $stats = [
            'total_bids' => $totalBids,
            'bids_won' => $wonBids,
            'win_rate' => $winRate,
            'total_revenue' => $this->shortMoney((float) $totalRevenue),
            'avg_rating' => $this->resolveAvgRating($companyId),
            'rating_count' => $this->resolveRatingCount($companyId),
            'bids_growth' => $bidsGrowth,
            'revenue_growth' => $revenueGrowth,
        ];

        $monthly = collect(range(5, 0))->map(function ($monthsBack) use ($companyId) {
            $month = now()->subMonths($monthsBack);

            $submitted = Bid::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $won = Bid::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('status', BidStatus::ACCEPTED->value)
                ->whereYear('updated_at', $month->year)
                ->whereMonth('updated_at', $month->month)
                ->count();

            $revenue = Payment::query()
                ->when($companyId, fn ($q) => $q->where('recipient_company_id', $companyId))
                ->where('status', 'completed')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_amount');

            return [
                'label' => $month->format('M Y'),
                'submitted' => $submitted,
                'won' => $won,
                'win_rate' => $submitted > 0 ? round(($won / $submitted) * 100, 1) : 0,
                'revenue' => $this->shortMoney((float) $revenue),
            ];
        })->all();

        $quality = $this->qualityMetrics($companyId, true);

        return view('dashboard.performance.index', [
            'stats' => $stats,
            'monthly' => $monthly,
            'quality' => $quality,
            'role' => 'supplier',
        ]);
    }

    /**
     * Buyer metrics: how many PRs created, RFQs published, contracts signed,
     * and how much was spent. Monthly chart shows spend.
     */
    private function buyerPerformance(?int $companyId): View
    {
        $totalPRs = PurchaseRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $totalRfqs = Rfq::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $totalContracts = Contract::query()
            ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))
            ->count();

        $totalSpend = Payment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'completed')
            ->sum('total_amount');

        // Real month-over-month growth for the headline KPIs.
        $prGrowth = $this->growthPercent(PurchaseRequest::class, $companyId, 'company_id', 'created_at');
        $spendGrowth = $this->growthPercent(Payment::class, $companyId, 'company_id', 'created_at', 'total_amount', ['status' => 'completed']);

        // For the buyer view we keep the same view template but reuse its
        // KPI slots with buyer-relevant numbers.
        $stats = [
            'total_bids' => $totalPRs,           // → "Purchase Requests"
            'bids_won' => $totalRfqs,          // → "RFQs Published"
            'win_rate' => $totalContracts,     // → "Active Contracts"
            'total_revenue' => $this->shortMoney((float) $totalSpend),  // → "Total Spend"
            'avg_rating' => $this->resolveAvgRating($companyId),
            'rating_count' => $this->resolveRatingCount($companyId),
            'bids_growth' => $prGrowth,
            'revenue_growth' => $spendGrowth,
        ];

        $monthly = collect(range(5, 0))->map(function ($monthsBack) use ($companyId) {
            $month = now()->subMonths($monthsBack);

            $prs = PurchaseRequest::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $rfqs = Rfq::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $spend = Payment::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('status', 'completed')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_amount');

            return [
                'label' => $month->format('M Y'),
                'submitted' => $prs,
                'won' => $rfqs,
                'win_rate' => 0,
                'revenue' => $this->shortMoney((float) $spend),
            ];
        })->all();

        $quality = $this->qualityMetrics($companyId, false);

        return view('dashboard.performance.index', [
            'stats' => $stats,
            'monthly' => $monthly,
            'quality' => $quality,
            'role' => 'buyer',
        ]);
    }

    /**
     * Generic month-over-month growth percentage. Compares the current
     * calendar month against the previous one and returns a signed integer.
     * Returns null when there's no prior data to compare against, so the
     * view can hide the badge instead of showing a misleading "0%".
     *
     * @param  class-string  $modelClass
     * @param  array<string,mixed>  $extraWhere
     */
    private function growthPercent(
        string $modelClass,
        ?int $companyId,
        string $companyColumn,
        string $dateColumn,
        ?string $sumColumn = null,
        array $extraWhere = []
    ): ?int {
        $build = function (CarbonInterface $month) use ($modelClass, $companyId, $companyColumn, $dateColumn, $sumColumn, $extraWhere) {
            $q = $modelClass::query()
                ->when($companyId, fn ($q) => $q->where($companyColumn, $companyId))
                ->whereYear($dateColumn, $month->year)
                ->whereMonth($dateColumn, $month->month);

            foreach ($extraWhere as $col => $val) {
                $q->where($col, $val);
            }

            return $sumColumn ? (float) $q->sum($sumColumn) : (float) $q->count();
        };

        $current = $build(now());
        $previous = $build(now()->subMonthNoOverflow());

        if ($previous <= 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    /**
     * Quality metrics derived from real on-time delivery + feedback rows.
     * Falls back to nulls (which the view should hide) instead of fabricating
     * "94.2% on-time" numbers.
     *
     * @return array{on_time:?float, customer_satisfaction:?float, satisfaction_count:int}
     */
    private function qualityMetrics(?int $companyId, bool $isSupplier): array
    {
        if (! $companyId) {
            return ['on_time' => null, 'customer_satisfaction' => null, 'satisfaction_count' => 0];
        }

        // On-time rate: percentage of delivered shipments where the actual
        // delivery happened on or before the estimated delivery date. We
        // scope to the company that owns the shipment in either role.
        $onTime = null;
        try {
            $shipQuery = Shipment::query()
                ->where('status', ShipmentStatus::DELIVERED->value)
                ->where('company_id', $companyId)
                ->whereNotNull('estimated_delivery')
                ->whereNotNull('actual_delivery');

            $totalDelivered = (clone $shipQuery)->count();
            if ($totalDelivered > 0) {
                $onTimeCount = (clone $shipQuery)
                    ->whereColumn('actual_delivery', '<=', 'estimated_delivery')
                    ->count();
                $onTime = round(($onTimeCount / $totalDelivered) * 100, 1);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Customer satisfaction: weighted average of feedback ratings.
        $satisfaction = $this->resolveAvgRating($companyId);
        $satisfactionCount = $this->resolveRatingCount($companyId);

        return [
            'on_time' => $onTime,
            'customer_satisfaction' => $satisfaction,
            'satisfaction_count' => $satisfactionCount,
        ];
    }

    private function resolveAvgRating(?int $companyId): ?float
    {
        if (! $companyId || ! Schema::hasTable('feedback')) {
            return null;
        }

        $avg = \DB::table('feedback')
            ->where('target_company_id', $companyId)
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    private function resolveRatingCount(?int $companyId): int
    {
        if (! $companyId || ! Schema::hasTable('feedback')) {
            return 0;
        }

        return (int) \DB::table('feedback')
            ->where('target_company_id', $companyId)
            ->count();
    }

    private function shortMoney(float $value, string $currency = 'AED'): string
    {
        if ($value >= 1_000_000) {
            return $currency.' '.round($value / 1_000_000, 1).'M';
        }
        if ($value >= 1_000) {
            return $currency.' '.round($value / 1_000).'K';
        }

        return $currency.' '.number_format($value);
    }
}
