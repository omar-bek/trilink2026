<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ExportsCsv;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Jobs\IssueTaxInvoiceJob;
use App\Models\Payment;
use App\Models\TaxInvoice;
use App\Services\PaymentService;
use App\Services\Tax\TaxInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    use FormatsForViews, ExportsCsv;

    public function __construct(private readonly PaymentService $service)
    {
    }

    public function index(Request $request): View|StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $companyId = $this->currentCompanyId();

        // Company-centric scope: a payment is visible if THIS company is on
        // either side of it — paying (company_id) OR receiving
        // (recipient_company_id). The previous filter only matched the
        // paying side, which silently hid every incoming payment from the
        // supplier dashboard. The show() endpoint already accepts both
        // sides; this brings the index in line with that.
        $base = Payment::query()->when($companyId, fn ($q) => $q->where(function ($qq) use ($companyId) {
            $qq->where('company_id', $companyId)
               ->orWhere('recipient_company_id', $companyId);
        }));

        $pendingStatuses   = [PaymentStatus::PENDING_APPROVAL->value, PaymentStatus::APPROVED->value, PaymentStatus::PROCESSING->value];
        $completedStatuses = [PaymentStatus::COMPLETED->value];

        // Tab filter from query string (?tab=pending|completed|all). The
        // active tab narrows the list but stat cards stay global so the
        // user always sees the headline numbers regardless of filter.
        $tab = $request->query('tab', 'all');
        if (! in_array($tab, ['all', 'pending', 'completed'], true)) {
            $tab = 'all';
        }

        // Free-text search (?q=...) over payment number, contract number,
        // milestone label and recipient company name.
        $search = trim((string) $request->query('q', ''));

        $listing = (clone $base);

        if ($tab === 'pending') {
            $listing->whereIn('status', $pendingStatuses);
        } elseif ($tab === 'completed') {
            $listing->whereIn('status', $completedStatuses);
        }

        if ($search !== '') {
            $listing->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('milestone', 'like', $like)
                    ->orWhereHas('contract', fn ($c) => $c->where('contract_number', 'like', $like))
                    ->orWhereHas('recipientCompany', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        // CSV export hook (Phase 0 / task 0.9). Honours the active tab + search
        // so the user gets exactly what they see on screen.
        if ($this->isCsvExport($request)) {
            $rows = (clone $listing)->with(['contract', 'recipientCompany'])->latest()->get()
                ->map(fn (Payment $p) => [
                    'id'             => $p->id,
                    'payment_number' => $this->paymentNumber($p),
                    'contract'       => $p->contract?->contract_number ?? '',
                    'recipient'      => $p->recipientCompany?->name ?? '',
                    'milestone'      => $p->milestone ?? '',
                    'amount'         => (float) $p->amount,
                    'vat'            => (float) $p->vat_amount,
                    'total'          => (float) $p->total_amount,
                    'currency'       => $p->currency,
                    'status'         => $this->statusValue($p->status),
                    'gateway'        => $p->payment_gateway ?? '',
                    'created_at'     => $p->created_at?->toDateTimeString(),
                ]);

            return $this->streamCsv($rows, 'payments');
        }

        $pendingAmount = (clone $base)->whereIn('status', $pendingStatuses)->sum('total_amount');
        $monthAmount   = (clone $base)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // Tab counts are global (not narrowed by search) so the user can
        // tell at a glance how many records each tab holds.
        $tabCounts = [
            'all'       => (clone $base)->count(),
            'pending'   => (clone $base)->whereIn('status', $pendingStatuses)->count(),
            'completed' => (clone $base)->whereIn('status', $completedStatuses)->count(),
        ];

        $stats = [
            'pending'        => $tabCounts['pending'],
            'pending_amount' => $this->shortMoney((float) $pendingAmount),
            'completed'      => $tabCounts['completed'],
            'paid_month'     => $this->shortMoney((float) $monthAmount),
        ];

        $payments = (clone $listing)
            ->with(['contract', 'recipientCompany'])
            ->latest()
            ->get()
            ->map(function (Payment $p) {
                $statusKey = $this->mapPaymentStatus($this->statusValue($p->status));
                $isPaid    = $statusKey === 'paid';
                $dueDate   = $p->approved_at ?? $p->created_at;
                $isOverdue = !$isPaid && $dueDate && $dueDate->isPast() && $dueDate->diffInDays(now()) > 7;
                $contractTotal = (float) ($p->contract?->total_amount ?? 0);
                $pct = $contractTotal > 0 ? (int) round(((float) $p->amount / $contractTotal) * 100) : 0;

                return [
                    // Real DB id used for routing to the show page.
                    'db_id'     => $p->id,
                    // Display label.
                    'id'        => $this->paymentNumber($p),
                    // Payment method is the gateway used to settle the
                    // payment when known, falling back to the platform
                    // default (Bank Transfer) for milestones that haven't
                    // been processed yet.
                    'method'    => $p->payment_gateway
                        ? ucfirst(str_replace('_', ' ', $p->payment_gateway))
                        : __('payments.bank_transfer'),
                    'contract'  => $p->contract?->contract_number ?? '—',
                    'supplier'  => $p->recipientCompany?->name ?? '—',
                    'milestone' => $p->milestone ?? __('payments.payment'),
                    'pct'       => $pct,
                    'of'        => $contractTotal,
                    'amount'    => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                    'due'       => $this->longDate($dueDate),
                    'status'    => $statusKey,
                    'urgent'    => $isOverdue,
                    'paid'      => $isPaid,
                ];
            })
            ->toArray();

        return view('dashboard.payments.index', compact('stats', 'payments', 'tab', 'tabCounts', 'search'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $p = Payment::with(['contract', 'recipientCompany', 'company', 'buyer'])->findOrFail((int) $id);

        // Authorization (IDOR fix): only the paying company, the recipient
        // company, or an admin/government user may view a payment row.
        // Without this check any authenticated user could enumerate the
        // payment ledger by guessing numeric ids.
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isGovernment()
            && $user->company_id !== $p->company_id
            && $user->company_id !== $p->recipient_company_id
        ) {
            abort(404);
        }

        $statusKey = $this->mapPaymentStatus($this->statusValue($p->status));
        $contractTotal = (float) ($p->contract?->total_amount ?? 0);
        $pct = $contractTotal > 0 ? (int) round(((float) $p->amount / $contractTotal) * 100) : 0;

        $payment = [
            'db_id'     => $p->id,
            'id'        => $this->paymentNumber($p),
            'status'    => $statusKey,
            'paid'      => $statusKey === 'paid',
            'method'    => $p->payment_gateway
                ? ucfirst(str_replace('_', ' ', $p->payment_gateway))
                : __('payments.bank_transfer'),
            'milestone' => $p->milestone ?? '—',
            'pct'       => $pct,
            'contract'  => $p->contract?->contract_number ?? '—',
            'contract_id'  => $p->contract?->id,
            'contract_url' => $p->contract
                ? route('dashboard.contracts.show', ['id' => $p->contract->id])
                : null,
            'supplier'  => $p->recipientCompany?->name ?? '—',
            'buyer'     => $p->company?->name ?? '—',
            'amount'    => $this->money((float) $p->amount, $p->currency ?? 'AED'),
            'vat'       => $this->money((float) $p->vat_amount, $p->currency ?? 'AED'),
            'total'     => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
            'due'       => $this->longDate($p->approved_at ?? $p->created_at),
            'created'   => $this->longDate($p->created_at),
            'gateway_ref' => $p->gateway_payment_id ?? '—',
        ];

        // Activity timeline — built from the audit log if any rows exist for
        // this payment, otherwise from a couple of model timestamps so the
        // section never appears empty.
        $timeline = $this->buildTimeline($p);

        // Phase 1 (UAE Compliance Roadmap) — surface the tax invoice that
        // was auto-issued by PaymentInvoiceObserver when the payment
        // completed. Buyers and suppliers both need to see it for
        // input-tax recovery and VAT-return evidence.
        $taxInvoice = TaxInvoice::where('payment_id', $p->id)
            ->where('status', '!=', TaxInvoice::STATUS_VOIDED)
            ->latest('id')
            ->first();

        $taxInvoiceView = $taxInvoice ? [
            'id'             => $taxInvoice->id,
            'invoice_number' => $taxInvoice->invoice_number,
            'issue_date'     => $this->longDate($taxInvoice->issue_date),
            'total'          => $this->money((float) $taxInvoice->total_inclusive, $taxInvoice->currency ?? 'AED'),
            'vat'            => $this->money((float) $taxInvoice->total_tax, $taxInvoice->currency ?? 'AED'),
            'download_url'   => route('dashboard.payments.invoice.download', ['id' => $p->id]),
            'has_pdf'        => (bool) $taxInvoice->pdf_path,
        ] : null;

        return view('dashboard.payments.show', compact('payment', 'timeline', 'taxInvoiceView'));
    }

    /**
     * Download the tax invoice attached to a payment. Only the paying
     * company, the recipient company, admins, or government auditors may
     * download. This is the user-facing counterpart of the admin route at
     * admin.tax-invoices.download — it resolves the invoice via the
     * payment id so buyers/suppliers don't need to know tax_invoice ids.
     */
    public function downloadInvoice(string $id, TaxInvoiceService $taxService): StreamedResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('payment.view'), 403);

        $payment = Payment::findOrFail((int) $id);

        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isGovernment()
            && $user->company_id !== $payment->company_id
            && $user->company_id !== $payment->recipient_company_id
        ) {
            abort(404);
        }

        $invoice = TaxInvoice::where('payment_id', $payment->id)
            ->where('status', '!=', TaxInvoice::STATUS_VOIDED)
            ->latest('id')
            ->first();

        if (!$invoice) {
            return back()->withErrors([
                'invoice' => __('tax_invoices.not_yet_issued'),
            ]);
        }

        // Lazy re-render: if the PDF is missing from disk (job crashed
        // mid-render, file was cleaned up, ...) regenerate it instead of
        // failing. Matches the admin download behaviour so buyers and
        // suppliers don't get stuck waiting for finance to intervene.
        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            $invoice = $taxService->renderAndStorePdf($invoice);
        }

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            $invoice->invoice_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Manually re-queue the tax invoice issuance job for a payment whose
     * auto-issuance failed (or which was completed before Phase 1 shipped).
     * Only the paying company or an admin may trigger this — the supplier
     * shouldn't be able to force-issue an invoice against their own books.
     */
    public function issueInvoice(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('payment.process'), 403);

        $payment = Payment::findOrFail((int) $id);

        $user = auth()->user();
        if (!$user->isAdmin() && $user->company_id !== $payment->company_id) {
            abort(403);
        }

        if ($payment->status !== PaymentStatus::COMPLETED) {
            return back()->withErrors([
                'invoice' => __('tax_invoices.only_completed'),
            ]);
        }

        IssueTaxInvoiceJob::dispatch($payment->id, $user->id);

        return back()->with('status', __('tax_invoices.issue_queued'));
    }

    /**
     * Build a small activity timeline for the show page. We don't have an
     * Observer wired on Payment yet (Phase 0 task 0.11), so this is derived
     * purely from model timestamps + status — created → approved → completed.
     *
     * @return array<int, array{label:string, time:string, color:string}>
     */
    private function buildTimeline(Payment $p): array
    {
        $events = [];

        $events[] = [
            'label' => __('payments.event.created'),
            'time'  => $this->longDate($p->created_at),
            'color' => '#4f7cff',
        ];

        if ($p->approved_at) {
            $events[] = [
                'label' => __('payments.event.approved'),
                'time'  => $this->longDate($p->approved_at),
                'color' => '#ffb020',
            ];
        }

        $statusValue = $p->status?->value;

        if ($statusValue === PaymentStatus::PROCESSING->value) {
            $events[] = [
                'label' => __('payments.event.processing'),
                'time'  => $this->longDate($p->updated_at),
                'color' => '#4f7cff',
            ];
        }

        if ($statusValue === PaymentStatus::COMPLETED->value && $p->updated_at) {
            $events[] = [
                'label' => __('payments.event.completed'),
                'time'  => $this->longDate($p->updated_at),
                'color' => '#00d9b5',
            ];
        }

        if (in_array($statusValue, [PaymentStatus::FAILED->value, PaymentStatus::REJECTED->value, PaymentStatus::CANCELLED->value], true)) {
            $events[] = [
                'label' => __('payments.event.failed'),
                'time'  => $this->longDate($p->updated_at),
                'color' => '#ff4d7f',
            ];
        }

        if ($statusValue === PaymentStatus::REFUNDED->value) {
            $events[] = [
                'label' => __('payments.event.refunded'),
                'time'  => $this->longDate($p->updated_at),
                'color' => '#8B5CF6',
            ];
        }

        return $events;
    }

    public function approve(string $id): RedirectResponse
    {
        $payment = Payment::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('payment.approve'), 403, 'Forbidden: missing payments.approve permission.');
        abort_unless($payment->company_id === $user->company_id, 403);

        $this->service->approve($payment->id, $user->id);

        return redirect()
            ->route('dashboard.payments')
            ->with('status', __('payments.approved_successfully'));
    }

    public function process(string $id): RedirectResponse
    {
        $payment = Payment::findOrFail((int) $id);
        $user = auth()->user();

        abort_unless($user?->hasPermission('payment.process'), 403, 'Forbidden: missing payments.process permission.');
        abort_unless($payment->company_id === $user->company_id, 403);

        $result = $this->service->process($payment->id, request()->input('gateway', 'stripe'));

        if (is_string($result)) {
            return back()->withErrors(['payment' => $result]);
        }

        return redirect()
            ->route('dashboard.payments')
            ->with('status', __('payments.processed_successfully'));
    }

    private function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'completed'        => 'paid',
            'pending_approval' => 'scheduled',
            'approved',
            'processing'       => 'due_soon',
            'failed',
            'rejected',
            'cancelled'        => 'urgent',
            'refunded'         => 'paid',
            default            => 'scheduled',
        };
    }

    /**
     * Display label for a payment. We don't have a `payment_number` column
     * yet so we synthesise one from the year of creation + the DB id. This
     * keeps the format stable across index/show/export views.
     */
    private function paymentNumber(Payment $p): string
    {
        $year = $p->created_at?->format('Y') ?? date('Y');

        return sprintf('PAY-%s-%04d', $year, $p->id);
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
