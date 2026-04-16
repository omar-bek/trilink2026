<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\IcvCertificate;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\SanctionsScreening;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Government console — escalated disputes queue + market intelligence rollups,
 * plus comprehensive reporting for regulatory oversight.
 */
class GovernmentController extends Controller
{
    use FormatsForViews;

    /**
     * Driver-agnostic "YYYY-MM" expression for the given column. MySQL
     * uses DATE_FORMAT, SQLite (used by the feature test suite) uses
     * strftime. Centralised so every monthly-trend query below stays
     * portable.
     */
    private function monthExpr(string $column = 'created_at'): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function index(): View
    {
        $stats = [
            'escalated' => Dispute::where('escalated_to_government', true)->whereNotIn('status', ['resolved'])->count(),
            'resolved' => Dispute::where('status', 'resolved')->count(),
            'companies' => Company::count(),
            'gmv' => (float) Contract::where('status', 'active')->sum('total_amount'),
            'vat_collected' => (float) Payment::where('status', 'completed')->sum('vat_amount'),
        ];

        $escalated = Dispute::with(['contract', 'company', 'againstCompany', 'raisedByUser'])
            ->where('escalated_to_government', true)
            ->whereNotIn('status', ['resolved'])
            ->latest()
            ->get();

        // Monthly GMV trend (12 months)
        $gmvTrend = DB::table('contracts')
            ->select(DB::raw($this->monthExpr().' as month'), DB::raw('SUM(total_amount) as value'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent companies
        $recentCompanies = Company::orderByDesc('created_at')->limit(5)->get(['id', 'name', 'type', 'status', 'created_at']);

        // Top contracts by value
        $topContracts = Contract::with('buyerCompany')
            ->where('status', 'active')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        return view('dashboard.gov.index', compact('stats', 'escalated', 'gmvTrend', 'recentCompanies', 'topContracts'));
    }

    /** Government: Contracts overview (read-only) */
    public function contracts(Request $request): View
    {
        $query = Contract::with(['buyerCompany'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%");
            });
        }

        $contracts = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => Contract::count(),
            'active' => Contract::where('status', 'active')->count(),
            'value' => (float) Contract::where('status', 'active')->sum('total_amount'),
            'signed' => Contract::where('status', 'signed')->count(),
        ];

        return view('dashboard.gov.contracts', compact('contracts', 'stats'));
    }

    /** Government: Payments overview (read-only) */
    public function payments(Request $request): View
    {
        $query = Payment::with(['contract'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20)->withQueryString();

        $stats = [
            'total_payments' => Payment::count(),
            'completed' => Payment::where('status', 'completed')->count(),
            'total_amount' => (float) Payment::where('status', 'completed')->sum('amount'),
            'total_vat' => (float) Payment::where('status', 'completed')->sum('vat_amount'),
        ];

        // Monthly payment trend
        $paymentTrend = DB::table('payments')
            ->where('status', 'completed')
            ->select(DB::raw($this->monthExpr().' as month'), DB::raw('SUM(amount) as amount'), DB::raw('SUM(vat_amount) as vat'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('dashboard.gov.payments', compact('payments', 'stats', 'paymentTrend'));
    }

    /** Government: ICV report */
    public function icvReport(): View
    {
        $stats = [
            'total_certs' => IcvCertificate::count(),
            'verified' => IcvCertificate::where('status', 'verified')->count(),
            'avg_score' => round((float) IcvCertificate::where('status', 'verified')->avg('score'), 1),
            'expiring_soon' => IcvCertificate::where('status', 'verified')
                ->where('expires_date', '<=', now()->addDays(60))
                ->where('expires_date', '>', now())
                ->count(),
        ];

        // By issuer
        $byIssuer = DB::table('icv_certificates')
            ->where('status', 'verified')
            ->select('issuer', DB::raw('COUNT(*) as count'), DB::raw('AVG(score) as avg_score'))
            ->groupBy('issuer')
            ->orderByDesc('count')
            ->get();

        // Score distribution
        $scoreDistribution = DB::table('icv_certificates')
            ->where('status', 'verified')
            ->select(DB::raw("
                CASE
                    WHEN score >= 80 THEN '80-100'
                    WHEN score >= 60 THEN '60-79'
                    WHEN score >= 40 THEN '40-59'
                    WHEN score >= 20 THEN '20-39'
                    ELSE '0-19'
                END as bracket
            "), DB::raw('COUNT(*) as count'))
            ->groupBy('bracket')
            ->orderByDesc('bracket')
            ->get();

        return view('dashboard.gov.icv-report', compact('stats', 'byIssuer', 'scoreDistribution'));
    }

    /** Government: Competition monitoring */
    public function competition(): View
    {
        // Single-bid RFQs (weak competition indicator)
        $totalRfqs = Rfq::whereNotIn('status', ['draft', 'cancelled'])->count();
        $singleBidRfqs = DB::table('rfqs')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereIn('id', function ($q) {
                $q->select('rfq_id')
                    ->from('bids')
                    ->groupBy('rfq_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->count();
        $noBidRfqs = DB::table('rfqs')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotIn('id', function ($q) {
                $q->select('rfq_id')->from('bids');
            })
            ->count();

        // Average bids per RFQ
        $avgBidsPerRfq = round((float) DB::table('bids')
            ->selectRaw('AVG(bid_count) as avg_bids')
            ->fromSub(
                DB::table('bids')->select('rfq_id', DB::raw('COUNT(*) as bid_count'))->groupBy('rfq_id'),
                'counts'
            )
            ->value('avg_bids'), 1);

        // Top suppliers by contract value (market concentration)
        $topSuppliers = DB::table('contracts')
            ->join('companies', function ($j) {
                $j->whereRaw("JSON_CONTAINS(contracts.parties, JSON_OBJECT('company_id', companies.id))")
                    ->where('companies.type', 'supplier');
            })
            ->where('contracts.status', 'active')
            ->select('companies.id', 'companies.name', DB::raw('COUNT(*) as contract_count'), DB::raw('SUM(contracts.total_amount) as total_value'))
            ->groupBy('companies.id', 'companies.name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $totalMarketValue = (float) Contract::where('status', 'active')->sum('total_amount');

        // Category concentration
        $byCategory = DB::table('rfqs')
            ->join('categories', 'rfqs.category_id', '=', 'categories.id')
            ->whereNotIn('rfqs.status', ['draft', 'cancelled'])
            ->select('categories.name', DB::raw('COUNT(*) as rfq_count'))
            ->groupBy('categories.name')
            ->orderByDesc('rfq_count')
            ->limit(10)
            ->get();

        return view('dashboard.gov.competition', compact(
            'totalRfqs', 'singleBidRfqs', 'noBidRfqs', 'avgBidsPerRfq',
            'topSuppliers', 'totalMarketValue', 'byCategory'
        ));
    }

    /** Government: Collusion & fraud report */
    public function collusionReport(): View
    {
        $stats = [
            'total_alerts' => (int) DB::table('collusion_alerts')->count(),
            'open' => (int) DB::table('collusion_alerts')->where('status', 'open')->count(),
            'confirmed' => (int) DB::table('collusion_alerts')->where('status', 'confirmed')->count(),
            'investigating' => (int) DB::table('collusion_alerts')->where('status', 'investigating')->count(),
        ];

        $bySeverity = DB::table('collusion_alerts')
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get()
            ->pluck('count', 'severity');

        $byType = DB::table('collusion_alerts')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        $recentAlerts = DB::table('collusion_alerts')
            ->join('rfqs', 'collusion_alerts.rfq_id', '=', 'rfqs.id')
            ->select('collusion_alerts.*', 'rfqs.rfq_number', 'rfqs.title as rfq_title')
            ->orderByDesc('collusion_alerts.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->evidence = json_decode($row->evidence, true) ?? [];

                return $row;
            });

        return view('dashboard.gov.collusion-report', compact('stats', 'bySeverity', 'byType', 'recentAlerts'));
    }

    /** Government: ESG & sustainability */
    public function esgReport(): View
    {
        $esgStats = [
            'companies_with_esg' => DB::table('esg_questionnaires')->distinct('company_id')->count('company_id'),
            'avg_environmental' => round((float) DB::table('esg_questionnaires')->avg('environmental_score'), 1),
            'avg_social' => round((float) DB::table('esg_questionnaires')->avg('social_score'), 1),
            'avg_governance' => round((float) DB::table('esg_questionnaires')->avg('governance_score'), 1),
            'avg_overall' => round((float) DB::table('esg_questionnaires')->avg('overall_score'), 1),
        ];

        // Carbon footprint
        $carbonStats = [
            'total_co2e_tonnes' => round((float) DB::table('carbon_footprints')->sum('co2e_kg') / 1000, 1),
            'scope1' => round((float) DB::table('carbon_footprints')->where('scope', 1)->sum('co2e_kg') / 1000, 1),
            'scope2' => round((float) DB::table('carbon_footprints')->where('scope', 2)->sum('co2e_kg') / 1000, 1),
            'scope3' => round((float) DB::table('carbon_footprints')->where('scope', 3)->sum('co2e_kg') / 1000, 1),
        ];

        // Modern slavery
        $slaveryStats = [
            'statements_filed' => DB::table('modern_slavery_statements')->count(),
            'board_approved' => DB::table('modern_slavery_statements')->where('board_approved', true)->count(),
        ];

        // Conflict minerals
        $mineralsStats = [
            'declarations' => DB::table('conflict_minerals_declarations')->count(),
            'conflict_free' => DB::table('conflict_minerals_declarations')
                ->where('tin_status', 'conflict_free')
                ->where('tungsten_status', 'conflict_free')
                ->where('tantalum_status', 'conflict_free')
                ->where('gold_status', 'conflict_free')
                ->count(),
        ];

        // Grade distribution
        $gradeDistribution = DB::table('esg_questionnaires')
            ->select('grade', DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        return view('dashboard.gov.esg-report', compact('esgStats', 'carbonStats', 'slaveryStats', 'mineralsStats', 'gradeDistribution'));
    }

    /** Government: Sanctions & compliance */
    public function sanctionsReport(): View
    {
        $stats = [
            'total_screenings' => SanctionsScreening::count(),
            'clean' => SanctionsScreening::where('result', 'clean')->count(),
            'hits' => SanctionsScreening::where('result', 'hit')->count(),
            'reviews' => SanctionsScreening::where('result', 'review')->count(),
            'errors' => SanctionsScreening::where('result', 'error')->count(),
        ];

        // Recent hits
        $recentHits = SanctionsScreening::with('company')
            ->whereIn('result', ['hit', 'review'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Monthly screening trend
        $screeningTrend = DB::table('sanctions_screenings')
            ->select(DB::raw($this->monthExpr().' as month'), DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN result = 'hit' THEN 1 ELSE 0 END) as hits"))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('dashboard.gov.sanctions-report', compact('stats', 'recentHits', 'screeningTrend'));
    }

    /** Government: SME participation */
    public function smeReport(): View
    {
        $totalCompanies = Company::where('status', 'active')->count();

        // SME classification: companies with < 250 employees or annual revenue < 250M AED
        // Since we don't have employee_count directly, use contract values as proxy
        $companyValues = DB::table('companies')
            ->leftJoin('contracts', function ($j) {
                $j->on('companies.id', '=', 'contracts.buyer_company_id')
                    ->orWhereRaw("JSON_CONTAINS(contracts.parties, JSON_OBJECT('company_id', companies.id))");
            })
            ->where('companies.status', 'active')
            ->select('companies.id', 'companies.name', 'companies.type', 'companies.created_at',
                DB::raw('COUNT(contracts.id) as contract_count'),
                DB::raw('COALESCE(SUM(contracts.total_amount), 0) as total_value'))
            ->groupBy('companies.id', 'companies.name', 'companies.type', 'companies.created_at')
            ->orderByDesc('total_value')
            ->get();

        // Classify: top 20% by value = large, rest = SME
        $threshold = $companyValues->count() > 0 ? $companyValues->take((int) ceil($companyValues->count() * 0.2))->last()?->total_value ?? 0 : 0;

        $smeCount = $companyValues->where('total_value', '<', $threshold)->count();
        $largeCount = $companyValues->where('total_value', '>=', $threshold)->count();

        $smeContractValue = $companyValues->where('total_value', '<', $threshold)->sum('total_value');
        $largeContractValue = $companyValues->where('total_value', '>=', $threshold)->sum('total_value');
        $totalContractValue = $smeContractValue + $largeContractValue;

        // By type
        $byType = DB::table('companies')
            ->where('status', 'active')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        // New registrations (monthly trend)
        $registrationTrend = DB::table('companies')
            ->select(DB::raw($this->monthExpr().' as month'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('dashboard.gov.sme-report', compact(
            'totalCompanies', 'smeCount', 'largeCount',
            'smeContractValue', 'largeContractValue', 'totalContractValue',
            'byType', 'registrationTrend'
        ));
    }

    /** Government: Dispute management */
    public function disputes(Request $request): View
    {
        $query = Dispute::with(['contract', 'company', 'againstCompany', 'assignedTo', 'raisedByUser'])
            ->orderByDesc('created_at');

        if ($request->query('escalated_only')) {
            $query->where('escalated_to_government', true);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $disputes = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => Dispute::count(),
            'escalated' => Dispute::where('escalated_to_government', true)->whereNotIn('status', ['resolved'])->count(),
            'resolved' => Dispute::where('status', 'resolved')->count(),
            'avg_days' => round((float) Dispute::where('status', 'resolved')
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(DATEDIFF(resolved_at, created_at)) as d')
                ->value('d'), 1),
        ];

        // Resolution history (for precedents)
        $precedents = Dispute::where('status', 'resolved')
            ->whereNotNull('resolution')
            ->orderByDesc('resolved_at')
            ->limit(10)
            ->get(['id', 'title', 'type', 'resolution', 'resolved_at']);

        return view('dashboard.gov.disputes', compact('disputes', 'stats', 'precedents'));
    }

    /** Government: Export report as CSV */
    public function export(Request $request)
    {
        $type = $request->query('type', 'contracts');

        return match ($type) {
            'contracts' => $this->exportContracts(),
            'payments' => $this->exportPayments(),
            'companies' => $this->exportCompanies(),
            'disputes' => $this->exportDisputes(),
            'icv' => $this->exportIcv(),
            default => abort(404),
        };
    }

    private function exportContracts()
    {
        $rows = DB::table('contracts')
            ->select('id', 'contract_number', 'status', 'total_amount', 'currency', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $csv = "ID,Contract Number,Status,Amount,Currency,Created\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},{$r->contract_number},{$r->status},{$r->total_amount},{$r->currency},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gov_contracts_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportPayments()
    {
        $rows = DB::table('payments')
            ->select('id', 'status', 'amount', 'vat_amount', 'currency', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $csv = "ID,Status,Amount,VAT,Currency,Created\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},{$r->status},{$r->amount},{$r->vat_amount},{$r->currency},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gov_payments_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportCompanies()
    {
        $rows = DB::table('companies')
            ->select('id', 'name', 'type', 'status', 'verification_level', 'created_at')
            ->orderBy('name')
            ->get();

        $csv = "ID,Name,Type,Status,Verification Level,Created\n";
        foreach ($rows as $r) {
            $csv .= "{$r->id},\"{$r->name}\",{$r->type},{$r->status},{$r->verification_level},{$r->created_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gov_companies_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportDisputes()
    {
        $rows = DB::table('disputes')
            ->select('id', 'title', 'type', 'status', 'escalated_to_government', 'created_at', 'resolved_at')
            ->orderByDesc('created_at')
            ->get();

        $csv = "ID,Title,Type,Status,Escalated,Created,Resolved\n";
        foreach ($rows as $r) {
            $title = str_replace('"', '""', $r->title ?? '');
            $csv .= "{$r->id},\"{$title}\",{$r->type},{$r->status},{$r->escalated_to_government},{$r->created_at},{$r->resolved_at}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gov_disputes_'.date('Y-m-d').'.csv"',
        ]);
    }

    private function exportIcv()
    {
        $rows = DB::table('icv_certificates')
            ->join('companies', 'icv_certificates.company_id', '=', 'companies.id')
            ->select('icv_certificates.id', 'companies.name as company_name', 'icv_certificates.issuer', 'icv_certificates.score', 'icv_certificates.status', 'icv_certificates.expires_date')
            ->orderBy('companies.name')
            ->get();

        $csv = "ID,Company,Issuer,Score,Status,Expires\n";
        foreach ($rows as $r) {
            $name = str_replace('"', '""', $r->company_name);
            $csv .= "{$r->id},\"{$name}\",{$r->issuer},{$r->score},{$r->status},{$r->expires_date}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gov_icv_'.date('Y-m-d').'.csv"',
        ]);
    }
}
