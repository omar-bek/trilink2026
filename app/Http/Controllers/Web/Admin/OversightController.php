<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only system-wide oversight for the platform admin.
 *
 * Mutations stay inside the role-gated dashboards (buyer/supplier pages)
 * so the audit trail keeps a single canonical source of truth. This page
 * exists purely so an admin can pivot across every tenant's PRs / RFQs /
 * Bids / Contracts / Payments / Shipments / Disputes from one place.
 *
 * The view is a tabbed shell driven by `?scope=` so each list keeps its
 * own pagination + query string and the URL is shareable.
 */
class OversightController extends Controller
{
    private const SCOPES = [
        'purchase_requests',
        'rfqs',
        'bids',
        'contracts',
        'payments',
        'shipments',
        'disputes',
    ];

    public function index(Request $request): View
    {
        $scope = (string) $request->query('scope', 'purchase_requests');
        if (! in_array($scope, self::SCOPES, true)) {
            $scope = 'purchase_requests';
        }
        $q = trim((string) $request->query('q', ''));

        $rows = $this->loadScope($scope, $q);
        $totals = $this->scopeTotals();

        return view('dashboard.admin.oversight.index', [
            'scope' => $scope,
            'scopes' => self::SCOPES,
            'q' => $q,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * Build the paginated collection for whichever scope the admin picked.
     * Each branch eager-loads only what the matching table column needs so
     * the request stays cheap on production-sized data.
     */
    private function loadScope(string $scope, string $q)
    {
        return match ($scope) {
            'purchase_requests' => PurchaseRequest::query()
                ->with(['company', 'buyer'])
                ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'rfqs' => Rfq::query()
                ->with('company')
                ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                    $w->where('rfq_number', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%");
                }))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'bids' => Bid::query()
                ->with(['company', 'rfq'])
                ->when($q !== '', fn ($query) => $query->whereHas('rfq', fn ($r) => $r->where('rfq_number', 'like', "%{$q}%")))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'contracts' => Contract::query()
                ->with(['buyerCompany', 'purchaseRequest'])
                ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                    $w->where('contract_number', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%");
                }))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'payments' => Payment::query()
                ->with(['company', 'recipientCompany', 'contract'])
                ->when($q !== '', fn ($query) => $query->whereHas('contract', fn ($c) => $c->where('contract_number', 'like', "%{$q}%")))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'shipments' => Shipment::query()
                ->with(['company', 'logisticsCompany', 'contract'])
                ->when($q !== '', fn ($query) => $query->where('tracking_number', 'like', "%{$q}%"))
                ->latest()
                ->paginate(20)
                ->withQueryString(),

            'disputes' => Dispute::query()
                ->with(['company', 'againstCompany', 'raisedByUser', 'contract'])
                ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        };
    }

    /**
     * Counts beside each scope tab — cheap aggregates so the admin can see
     * which tables actually have data without clicking through every tab.
     */
    private function scopeTotals(): array
    {
        return [
            'purchase_requests' => PurchaseRequest::count(),
            'rfqs' => Rfq::count(),
            'bids' => Bid::count(),
            'contracts' => Contract::count(),
            'payments' => Payment::count(),
            'shipments' => Shipment::count(),
            'disputes' => Dispute::count(),
            'completed_payments' => (float) Payment::where('status', PaymentStatus::COMPLETED->value)->sum('total_amount'),
        ];
    }
}
