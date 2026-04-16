<?php

namespace App\Http\Controllers\Web\Contract;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Spend Analytics dashboard — buyer-side analytics over the tenant's
 * contracts. Extracted from ContractController to keep the parent class
 * focused on contract CRUD/signing/amendments.
 *
 * Tenant scope: same as the contracts index — each tenant sees only
 * contracts where their company appears on either side. Admin / government
 * users see everything via Contract::query() without scope.
 */
class AnalyticsController extends Controller
{
    use FormatsForViews;

    public function __invoke(): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('contract.view'), 403);

        $companyId = $this->currentCompanyId();

        // Tenant scope via the indexed contract_parties junction (synced
        // by ContractObserver from the canonical parties JSON) instead
        // of `whereJsonContains` which would full-table-scan.
        $base = Contract::query();
        if ($companyId) {
            $base->forCompany($companyId);
        }

        $now = now();
        $thisMonthFrom = $now->copy()->startOfMonth();
        $thisQtrFrom = $now->copy()->firstOfQuarter();
        $thisYearFrom = $now->copy()->startOfYear();

        // Spend KPIs.
        $totalAll = (float) (clone $base)->sum('total_amount');
        $totalThisMonth = (float) (clone $base)->where('created_at', '>=', $thisMonthFrom)->sum('total_amount');
        $totalThisQtr = (float) (clone $base)->where('created_at', '>=', $thisQtrFrom)->sum('total_amount');
        $totalThisYear = (float) (clone $base)->where('created_at', '>=', $thisYearFrom)->sum('total_amount');

        $statusCounts = (clone $base)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        // Average velocity = days between created_at and the first
        // signature in the signatures JSON. JSON paths can't be aggregated
        // portably, so iterate across the most recent 200 signed rows.
        $signedSample = (clone $base)
            ->whereNotNull('signatures')
            ->latest()
            ->limit(200)
            ->get(['id', 'created_at', 'signatures']);
        $velocityDays = [];
        foreach ($signedSample as $c) {
            $sigs = $c->signatures ?? [];
            if (empty($sigs)) {
                continue;
            }
            $firstAt = collect($sigs)->pluck('signed_at')->filter()->sort()->first();
            if (! $firstAt) {
                continue;
            }
            try {
                $diff = $c->created_at?->diffInDays(Carbon::parse($firstAt));
                if ($diff !== null) {
                    $velocityDays[] = (float) $diff;
                }
            } catch (\Throwable) {
            }
        }
        $avgVelocity = ! empty($velocityDays) ? round(array_sum($velocityDays) / count($velocityDays), 1) : null;

        // Top 5 suppliers by aggregated contract value — uses the indexed
        // contract_parties junction with a SQL GROUP BY rather than a
        // PHP-side reduce over JSON parties.
        $scopedIds = (clone $base)->pluck('id');
        $topSupplierRows = DB::table('contract_parties as cp')
            ->join('contracts as c', 'c.id', '=', 'cp.contract_id')
            ->join('companies as co', 'co.id', '=', 'cp.company_id')
            ->whereIn('cp.contract_id', $scopedIds)
            ->where('cp.role', 'supplier')
            ->groupBy('cp.company_id', 'co.name')
            ->orderByDesc(DB::raw('SUM(c.total_amount)'))
            ->limit(5)
            ->get(['cp.company_id', 'co.name', DB::raw('SUM(c.total_amount) as total')]);

        $topSuppliers = $topSupplierRows
            ->map(fn ($r) => [
                'name' => $r->name ?? '—',
                'value' => $this->money((float) $r->total, 'AED'),
                'raw' => (float) $r->total,
            ])
            ->all();

        // 12-month spend timeseries.
        $twelveMonthsAgo = $now->copy()->subMonths(11)->startOfMonth();
        $rows = (clone $base)
            ->where('created_at', '>=', $twelveMonthsAgo)
            ->get(['created_at', 'total_amount']);
        $monthly = [];
        for ($m = 0; $m < 12; $m++) {
            $key = $twelveMonthsAgo->copy()->addMonths($m)->format('Y-m');
            $monthly[$key] = ['label' => $twelveMonthsAgo->copy()->addMonths($m)->format('M'), 'value' => 0.0];
        }
        foreach ($rows as $r) {
            $key = $r->created_at?->format('Y-m');
            if ($key && isset($monthly[$key])) {
                $monthly[$key]['value'] += (float) $r->total_amount;
            }
        }
        $monthlyMax = max(array_column($monthly, 'value')) ?: 1;

        return view('dashboard.contracts.analytics', [
            'kpis' => [
                'total_all' => $this->money($totalAll, 'AED'),
                'this_month' => $this->money($totalThisMonth, 'AED'),
                'this_qtr' => $this->money($totalThisQtr, 'AED'),
                'this_year' => $this->money($totalThisYear, 'AED'),
                'avg_velocity' => $avgVelocity,
            ],
            'status_counts' => $statusCounts,
            'top_suppliers' => $topSuppliers,
            'monthly' => array_values($monthly),
            'monthly_max' => $monthlyMax,
        ]);
    }
}
