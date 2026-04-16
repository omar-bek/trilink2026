<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SpendAnalyticsService;
use Illuminate\View\View;

/**
 * Read-only spend analytics dashboard for the buyer side. Aggregates the
 * company's contract history into KPIs, supplier concentration, monthly
 * trend, and category breakdown.
 *
 * Branch managers see only their branch's slice; company managers see the
 * full org rolled up.
 */
class SpendAnalyticsController extends Controller
{
    public function __construct(private readonly SpendAnalyticsService $service) {}

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user?->company_id, 403);
        abort_unless($user->hasPermission('reports.view'), 403);

        $companyId = $user->company_id;
        $branchId = $user->isBranchManager() ? $user->branch_id : null;

        $summary = $this->service->summary($companyId, $branchId);
        $topSuppliers = $this->service->topSuppliers($companyId, 10, $branchId);
        $monthlyTrend = $this->service->monthlyTrend($companyId, $branchId);
        $byCategory = $this->service->spendByCategory($companyId, $branchId);

        return view('dashboard.analytics.spend', compact(
            'summary',
            'topSuppliers',
            'monthlyTrend',
            'byCategory',
        ));
    }
}
