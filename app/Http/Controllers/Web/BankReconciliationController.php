<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase F — finance-team UI for importing MT940/CAMT.053 statements
 * and reconciling the resulting lines against platform rows
 * (Payments, EscrowReleases, BankGuaranteeCalls). Everything lives
 * under /dashboard/reconciliation and is gated by payment.view since
 * only finance users need it.
 */
class BankReconciliationController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly BankReconciliationService $service) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $companyId = $this->currentCompanyId();

        $statements = BankStatement::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->withCount(['lines', 'lines as matched_count' => fn ($q) => $q->where('match_status', 'matched')])
            ->latest('statement_date')
            ->paginate(15)
            ->withQueryString();

        $unmatched = BankStatementLine::query()
            ->where('match_status', 'unmatched')
            ->whereHas('statement', fn ($q) => $q->when($companyId, fn ($q) => $q->where('company_id', $companyId)))
            ->with('statement')
            ->latest('value_date')
            ->limit(50)
            ->get();

        $stats = [
            'statements' => $statements->total(),
            'unmatched' => $unmatched->count(),
            'matched_today' => BankStatementLine::where('match_status', 'matched')
                ->whereDate('matched_at', today())->count(),
        ];

        return view('dashboard.reconciliation.index', compact('statements', 'unmatched', 'stats'));
    }

    public function upload(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:txt,mt,sta,940'],
        ]);

        $user = auth()->user();
        $companyId = $this->currentCompanyId() ?? 0;
        $raw = file_get_contents($data['file']->getRealPath());

        try {
            $stmt = $this->service->importMt940($raw, $companyId, $user->id);

            return redirect()->route('dashboard.reconciliation.show', ['id' => $stmt->id])
                ->with('status', __('recon.imported', ['count' => $stmt->lines->count()]));
        } catch (\Throwable $e) {
            return back()->with('error', __('recon.import_failed').': '.$e->getMessage());
        }
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $stmt = BankStatement::with('lines')->findOrFail((int) $id);

        return view('dashboard.reconciliation.show', ['statement' => $stmt]);
    }

    /**
     * Manual match — finance user picks a payment/release to attach to
     * an unmatched line. The line's match_status flips to 'manual' so
     * the audit trail can distinguish auto-match from human match.
     */
    public function match(Request $request, string $lineId): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $data = $request->validate([
            'matched_type' => ['required', 'in:payment,escrow_release,bg_call'],
            'matched_id' => ['required', 'integer'],
        ]);

        $line = BankStatementLine::findOrFail((int) $lineId);
        $line->update([
            'matched_type' => $data['matched_type'],
            'matched_id' => $data['matched_id'],
            'match_status' => 'manual',
            'matched_by' => auth()->id(),
            'matched_at' => now(),
        ]);

        return back()->with('status', __('recon.matched'));
    }
}
