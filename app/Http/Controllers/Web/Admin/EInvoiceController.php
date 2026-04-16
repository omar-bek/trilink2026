<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EInvoiceSubmission;
use App\Services\EInvoice\EInvoiceDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 (UAE Compliance Roadmap) — admin-side e-invoice transmission
 * queue. Read-only listing + a "Retry" button for failed/rejected
 * rows. The actual submission lifecycle is owned by the dispatcher
 * and the ASP provider — this controller is only the UI shim.
 */
class EInvoiceController extends Controller
{
    public function __construct(
        private readonly EInvoiceDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payment.view'), 403);

        $query = EInvoiceSubmission::with(['taxInvoice:id,invoice_number,supplier_name,buyer_name,total_inclusive,currency'])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = $request->query('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('asp_submission_id', 'like', "%{$q}%")
                    ->orWhere('fta_clearance_id', 'like', "%{$q}%")
                    ->orWhereHas('taxInvoice', fn ($t) => $t->where('invoice_number', 'like', "%{$q}%"));
            });
        }

        $submissions = $query->paginate(20)->withQueryString();

        $stats = [
            'queued' => EInvoiceSubmission::where('status', EInvoiceSubmission::STATUS_QUEUED)->count(),
            'submitted' => EInvoiceSubmission::where('status', EInvoiceSubmission::STATUS_SUBMITTED)->count(),
            'accepted' => EInvoiceSubmission::where('status', EInvoiceSubmission::STATUS_ACCEPTED)->count(),
            'rejected' => EInvoiceSubmission::where('status', EInvoiceSubmission::STATUS_REJECTED)->count(),
            'failed' => EInvoiceSubmission::where('status', EInvoiceSubmission::STATUS_FAILED)->count(),
        ];

        return view('dashboard.admin.e-invoice.index', [
            'submissions' => $submissions,
            'stats' => $stats,
            'filters' => [
                'q' => $request->query('q'),
                'status' => $request->query('status'),
            ],
            'einvoiceEnabled' => $this->dispatcher->isEnabled(),
            'currentProvider' => (string) config('einvoice.default_provider', 'mock'),
            'currentEnv' => (string) config('einvoice.environment', 'sandbox'),
        ]);
    }

    public function retry(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.process'), 403);

        $submission = EInvoiceSubmission::findOrFail($id);

        if (! $submission->isRetryable()) {
            return back()->withErrors(['retry' => __('einvoice.only_failed_can_retry')]);
        }

        $this->dispatcher->retry($submission);

        return back()->with('status', __('einvoice.retry_dispatched'));
    }
}
