<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Payment;
use App\Models\TaxInvoice;
use App\Services\Tax\TaxInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-side management of tax invoices and credit notes.
 *
 * Phase 1 of the UAE Compliance Roadmap. The view is intentionally simple
 * — list, show, download, void, and manual reissue. Auto-issuance happens
 * via the PaymentInvoiceObserver pipeline; this controller exists for the
 * cases where finance needs to intervene (failed jobs, manual issue,
 * voiding an erroneous invoice).
 */
class TaxInvoiceController extends Controller
{
    use FormatsForViews;

    public function __construct(
        private readonly TaxInvoiceService $service,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payment.view'), 403);

        $query = TaxInvoice::query()
            ->with(['supplier:id,name,tax_number', 'buyer:id,name,tax_number'])
            ->latest('issue_date');

        if ($q = $request->query('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('invoice_number', 'like', "%{$q}%")
                    ->orWhere('supplier_name', 'like', "%{$q}%")
                    ->orWhere('buyer_name', 'like', "%{$q}%")
                    ->orWhere('supplier_trn', 'like', "%{$q}%")
                    ->orWhere('buyer_trn', 'like', "%{$q}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($year = $request->query('year')) {
            $query->whereYear('issue_date', (int) $year);
        }

        $invoices = $query->paginate(20)->withQueryString();

        // Aggregate stats for the header strip — total issued this month,
        // total voided, total VAT collected, etc.
        $stats = [
            'total_issued' => TaxInvoice::where('status', TaxInvoice::STATUS_ISSUED)->count(),
            'total_voided' => TaxInvoice::where('status', TaxInvoice::STATUS_VOIDED)->count(),
            'this_month' => TaxInvoice::whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->count(),
            'vat_this_month' => $this->money(
                (float) TaxInvoice::where('status', TaxInvoice::STATUS_ISSUED)
                    ->whereMonth('issue_date', now()->month)
                    ->whereYear('issue_date', now()->year)
                    ->sum('total_tax'),
                'AED'
            ),
        ];

        return view('dashboard.admin.tax-invoices.index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'filters' => [
                'q' => $request->query('q'),
                'status' => $request->query('status'),
                'year' => $request->query('year'),
            ],
        ]);
    }

    public function show(int $id, Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payment.view'), 403);

        $invoice = TaxInvoice::with([
            'supplier',
            'buyer',
            'contract',
            'payment',
            'issuer',
            'voider',
            'creditNotes',
        ])->findOrFail($id);

        return view('dashboard.admin.tax-invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function download(int $id, Request $request): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.view'), 403);

        $invoice = TaxInvoice::findOrFail($id);

        if (! $invoice->pdf_path) {
            // PDF was never rendered (job failed, or invoice issued before
            // the renderer existed). Render it now and store it.
            $invoice = $this->service->renderAndStorePdf($invoice);
        }

        if (! Storage::disk('local')->exists($invoice->pdf_path)) {
            return back()->withErrors([
                'pdf' => __('tax_invoices.pdf_missing'),
            ]);
        }

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            $invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Manually issue a tax invoice for a payment that should have one
     * but doesn't (the auto-issuance job failed, or the payment was
     * marked completed via a path that didn't fire the observer).
     */
    public function issueForPayment(Request $request, int $paymentId): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.process'), 403);

        $payment = Payment::findOrFail($paymentId);

        try {
            $invoice = $this->service->issueFor($payment, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['issue' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.tax-invoices.show', $invoice->id)
            ->with('status', __('tax_invoices.issued_successfully', ['number' => $invoice->invoice_number]));
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payment.process'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $invoice = TaxInvoice::findOrFail($id);
        $this->service->voidInvoice($invoice, $data['reason'], $request->user()->id);

        return back()->with('status', __('tax_invoices.voided_successfully'));
    }
}
