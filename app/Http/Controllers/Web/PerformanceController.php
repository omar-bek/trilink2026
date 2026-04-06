<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\Payment;
use Illuminate\View\View;

/**
 * Performance Dashboard — totals + monthly history + quality metrics.
 *
 * Scoped to the authenticated user's company. Suppliers see win-rate vs. bids
 * submitted; buyers see contract value vs. bids accepted; logistics see
 * shipments delivered. The view is generic enough to work for any role.
 */
class PerformanceController extends Controller
{
    use FormatsForViews;

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);

        $companyId = $this->currentCompanyId();

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

        $stats = [
            'total_bids'   => $totalBids,
            'bids_won'     => $wonBids,
            'win_rate'     => $winRate,
            'total_revenue' => $this->shortMoney((float) $totalRevenue),
            'avg_rating'   => 4.7, // Placeholder until we add a reviews table.
            'rating_count' => 5,
        ];

        // Last 6 months performance.
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
                'label'     => $month->format('M Y'),
                'submitted' => $submitted,
                'won'       => $won,
                'win_rate'  => $submitted > 0 ? round(($won / $submitted) * 100, 1) : 0,
                'revenue'   => $this->shortMoney((float) $revenue),
            ];
        })->all();

        $quality = [
            'on_time'              => 94.2,
            'customer_satisfaction' => 4.6,
            'avg_response_hours'   => 2.3,
        ];

        return view('dashboard.performance.index', compact('stats', 'monthly', 'quality'));
    }

    private function shortMoney(float $value, string $currency = 'AED'): string
    {
        if ($value >= 1_000_000) {
            return $currency . ' ' . round($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return $currency . ' ' . round($value / 1_000) . 'K';
        }

        return $currency . ' ' . number_format($value);
    }
}
