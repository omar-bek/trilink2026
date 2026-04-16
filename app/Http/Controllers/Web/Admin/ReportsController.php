<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $range = $request->query('range', '30');
        $since = now()->subDays((int) $range);

        // Monthly trend data (last 12 months). Driver-agnostic so feature
        // tests running on SQLite don't blow up on MySQL-only DATE_FORMAT.
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
        $monthlyTrend = DB::table('contracts')
            ->select(DB::raw("{$monthExpr} as month"), DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as value'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Supplier scorecard
        $supplierScorecard = DB::table('companies')
            ->where('companies.type', 'supplier')
            ->where('companies.status', 'active')
            ->select([
                'companies.id',
                'companies.name',
                'companies.verification_level',
            ])
            ->selectSub(
                Bid::selectRaw('COUNT(*)')->whereColumn('bids.company_id', 'companies.id'),
                'total_bids'
            )
            ->selectSub(
                Bid::selectRaw('COUNT(*)')->whereColumn('bids.company_id', 'companies.id')->where('status', 'accepted'),
                'won_bids'
            )
            ->selectSub(
                Contract::selectRaw('COUNT(*)')
                    ->whereRaw("JSON_CONTAINS(parties, JSON_OBJECT('company_id', companies.id))"),
                'total_contracts'
            )
            ->selectSub(
                Dispute::selectRaw('COUNT(*)')->whereColumn('against_company_id', 'companies.id'),
                'disputes_against'
            )
            ->orderByDesc('total_bids')
            ->limit(50)
            ->get();

        // Cycle time: avg days from RFQ publish to contract sign
        $cycleTime = DB::table('rfqs')
            ->join('bids', 'rfqs.id', '=', 'bids.rfq_id')
            ->join('contracts', function ($j) {
                $j->whereRaw("JSON_CONTAINS(contracts.parties, JSON_OBJECT('company_id', bids.company_id))");
            })
            ->where('rfqs.created_at', '>=', $since)
            ->selectRaw('AVG(DATEDIFF(contracts.created_at, rfqs.created_at)) as avg_days')
            ->value('avg_days');

        // Savings report
        $savingsData = DB::table('rfqs')
            ->join('bids', function ($j) {
                $j->on('rfqs.id', '=', 'bids.rfq_id')->where('bids.status', '=', 'accepted');
            })
            ->where('rfqs.created_at', '>=', $since)
            ->where('rfqs.budget', '>', 0)
            ->selectRaw('SUM(rfqs.budget) as total_budget, SUM(bids.amount) as total_awarded, COUNT(*) as count')
            ->first();

        // Platform summary stats
        $stats = [
            'total_gmv' => (float) Contract::where('status', 'active')->sum('total_amount'),
            'total_payments' => (float) Payment::where('status', 'completed')->sum('amount'),
            'total_vat' => (float) Payment::where('status', 'completed')->sum('vat_amount'),
            'active_companies' => Company::where('status', 'active')->count(),
            'open_rfqs' => Rfq::where('status', 'open')->count(),
            'active_contracts' => Contract::where('status', 'active')->count(),
            'open_disputes' => Dispute::whereNotIn('status', ['resolved'])->count(),
            'avg_cycle_days' => round((float) ($cycleTime ?? 0), 1),
        ];

        return view('dashboard.admin.reports.index', compact(
            'stats', 'monthlyTrend', 'supplierScorecard', 'savingsData', 'range'
        ));
    }

    public function export(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $type = $request->query('type', 'suppliers');

        if ($type === 'suppliers') {
            return $this->exportSuppliersCsv();
        }

        if ($type === 'contracts') {
            return $this->exportContractsCsv();
        }

        if ($type === 'payments') {
            return $this->exportPaymentsCsv();
        }

        abort(404);
    }

    private function exportSuppliersCsv()
    {
        $rows = DB::table('companies')
            ->where('type', 'supplier')
            ->where('status', 'active')
            ->select('id', 'name', 'verification_level', 'created_at')
            ->orderBy('name')
            ->get();

        $csv = "ID,Name,Verification Level,Registered\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},\"{$r->name}\",{$r->verification_level},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="suppliers_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportContractsCsv()
    {
        $rows = DB::table('contracts')
            ->select('id', 'contract_number', 'status', 'total_amount', 'currency', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        $csv = "ID,Contract Number,Status,Amount,Currency,Created\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},{$r->contract_number},{$r->status},{$r->total_amount},{$r->currency},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contracts_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportPaymentsCsv()
    {
        $rows = DB::table('payments')
            ->select('id', 'status', 'amount', 'vat_amount', 'currency', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        $csv = "ID,Status,Amount,VAT,Currency,Created\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},{$r->status},{$r->amount},{$r->vat_amount},{$r->currency},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments_'.date('Y-m-d').'.csv"',
        ]);
    }
}
