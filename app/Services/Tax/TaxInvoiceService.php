<?php

namespace App\Services\Tax;

use App\Enums\PaymentStatus;
use App\Jobs\SubmitEInvoiceJob;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Tax invoice & credit note issuance service.
 *
 * Single entry point for everything that has to do with creating, voiding,
 * or rendering UAE tax invoices. Designed to be called from:
 *
 *   - The IssueTaxInvoiceJob (auto-issue when a Payment becomes COMPLETED)
 *   - The Web TaxInvoiceController (manual issue from finance UI)
 *   - Tests + tinker for verification
 *
 * Idempotency rules:
 *
 *   - issueFor(Payment) is idempotent: if a non-voided invoice already
 *     exists for the same payment, it is returned unchanged. Two parallel
 *     callers will both end up with the same TaxInvoice instance.
 *   - voidInvoice cannot be undone (an issued invoice that gets voided
 *     stays voided). Re-running it on an already-voided invoice is a
 *     no-op.
 */
class TaxInvoiceService
{
    public function __construct(
        private readonly InvoiceNumberAllocator $allocator,
        private readonly TaxInvoiceQrEncoder $qrEncoder,
    ) {
    }

    /**
     * Issue a tax invoice for a completed Payment. Pulls the supplier from
     * the payment's recipient_company, the buyer from payment.company, and
     * computes the line items from the contract amounts JSON.
     *
     * Returns the existing invoice if one was already issued for this
     * payment — see the lock + select-existing dance below.
     */
    public function issueFor(Payment $payment, ?int $issuedBy = null): TaxInvoice
    {
        if ($payment->status !== PaymentStatus::COMPLETED) {
            throw new RuntimeException(
                "Cannot issue tax invoice for payment {$payment->id}: status is {$payment->status->value}, expected completed."
            );
        }

        // Idempotency check OUTSIDE the transaction first — most calls hit
        // this path and return immediately without touching the allocator.
        $existing = TaxInvoice::where('payment_id', $payment->id)
            ->where('status', '!=', TaxInvoice::STATUS_VOIDED)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($payment, $issuedBy) {
            // Recheck under transaction to defeat the race where two
            // concurrent callers both saw "no existing" outside the lock.
            // The unique key on tax_invoices.invoice_number would catch a
            // duplicate, but we'd rather avoid spending a sequence number.
            $existing = TaxInvoice::where('payment_id', $payment->id)
                ->where('status', '!=', TaxInvoice::STATUS_VOIDED)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $payment->loadMissing(['contract.buyerCompany', 'recipientCompany', 'company']);

            $supplier = $payment->recipientCompany;
            $buyer    = $payment->company; // The party that paid is the buyer.

            if (!$supplier || !$buyer) {
                throw new RuntimeException(
                    "Cannot issue invoice for payment {$payment->id}: missing supplier or buyer company."
                );
            }

            $contract = $payment->contract;

            $lineItems = $this->buildLineItemsForPayment($payment, $contract);

            // Phase 1.5 (UAE Compliance Roadmap — post-implementation
            // hardening). Resolve the VAT treatment so the PDF + the
            // e-invoice XML can render the correct legal marking. The
            // contract envelope already carries `vat_case` from the
            // Phase 3 ContractService work; legacy contracts (created
            // before Phase 3) fall back to 'standard' which is what
            // they always were anyway.
            $vatTreatment = $this->resolveVatTreatment($contract);

            // Recompute totals from the line items rather than trusting
            // the payment's stored vat_amount/total_amount blindly. This
            // catches any drift if the line item shape and the payment
            // totals disagree (which would itself be a bug, but we'd
            // rather catch it here than show wrong numbers on a tax doc).
            [$subtotal, $totalTax, $totalInclusive] = $this->totalsFromLineItems($lineItems);

            $invoiceNumber = $this->allocator->allocate(
                $supplier->id,
                InvoiceNumberAllocator::SERIES_INVOICE
            );

            $invoice = TaxInvoice::create([
                'invoice_number'      => $invoiceNumber,
                'contract_id'         => $contract?->id,
                'payment_id'          => $payment->id,
                'issue_date'          => CarbonImmutable::now()->toDateString(),
                'supply_date'         => $payment->approved_at?->toDateString() ?? CarbonImmutable::now()->toDateString(),
                'supplier_company_id' => $supplier->id,
                'supplier_trn'        => $supplier->tax_number,
                'supplier_name'       => $supplier->name,
                'supplier_address'    => $this->formatAddress($supplier),
                'supplier_country'    => $supplier->country,
                'buyer_company_id'    => $buyer->id,
                'buyer_trn'           => $buyer->tax_number,
                'buyer_name'          => $buyer->name,
                'buyer_address'       => $this->formatAddress($buyer),
                'buyer_country'       => $buyer->country,
                'line_items'          => $lineItems,
                'subtotal_excl_tax'   => $subtotal,
                'total_discount'      => 0,
                'total_tax'           => $totalTax,
                'total_inclusive'     => $totalInclusive,
                'currency'            => $payment->currency ?? 'AED',
                'vat_treatment'       => $vatTreatment,
                'status'              => TaxInvoice::STATUS_ISSUED,
                'issued_by'           => $issuedBy,
                'issued_at'           => now(),
            ]);

            // Render + persist the PDF inside the same transaction. If PDF
            // generation fails for any reason, the invoice insert rolls
            // back and the sequence number is wasted (acceptable cost —
            // gaps in the sequence are FTA-tolerated as long as we can
            // explain them).
            $this->renderAndStorePdf($invoice);

            // Phase 5 (UAE Compliance Roadmap) — kick off the FTA Peppol
            // submission asynchronously. The job is gated by
            // config('einvoice.enabled'); when the feature flag is off
            // it returns silently without writing to the DB. Dispatched
            // AFTER the transaction commits (afterCommit) so a queue
            // worker that picks the job up immediately doesn't see a
            // tax invoice that hasn't been persisted yet.
            \App\Jobs\SubmitEInvoiceJob::dispatch($invoice->id)->afterCommit();

            return $invoice->fresh();
        });
    }

    /**
     * Issue a tax credit note that reverses (or partially reverses) an
     * existing tax invoice. Used by the refund workflow + dispute
     * settlement workflow.
     *
     * @param  array<int, array<string, mixed>>|null  $lineItems  Pass null to reverse the full invoice
     */
    public function issueCreditNote(
        TaxInvoice $original,
        string $reason,
        ?array $lineItems = null,
        ?string $notes = null,
        ?int $issuedBy = null,
    ): TaxCreditNote {
        if (!in_array($reason, TaxCreditNote::REASONS, true)) {
            throw new RuntimeException("Invalid credit note reason: {$reason}");
        }

        return DB::transaction(function () use ($original, $reason, $lineItems, $notes, $issuedBy) {
            // Default to reversing the full invoice if the caller didn't
            // pass specific lines (the common case for "refund the whole
            // payment").
            $lineItems = $lineItems ?? $original->line_items;

            [$subtotal, $totalTax, $totalInclusive] = $this->totalsFromLineItems($lineItems);

            $creditNoteNumber = $this->allocator->allocate(
                $original->supplier_company_id,
                InvoiceNumberAllocator::SERIES_CREDIT_NOTE
            );

            $cn = TaxCreditNote::create([
                'credit_note_number'  => $creditNoteNumber,
                'original_invoice_id' => $original->id,
                'issue_date'          => CarbonImmutable::now()->toDateString(),
                'reason'              => $reason,
                'notes'               => $notes,
                'line_items'          => $lineItems,
                'subtotal_excl_tax'   => $subtotal,
                'total_tax'           => $totalTax,
                'total_inclusive'     => $totalInclusive,
                'currency'            => $original->currency,
                'issued_by'           => $issuedBy,
                'issued_at'           => now(),
            ]);

            $this->renderAndStoreCreditNotePdf($cn);

            // Phase 5.5 (UAE Compliance Roadmap — post-implementation
            // hardening). Credit notes ALSO need to flow through the
            // FTA Peppol clearance pipeline. Cabinet Decision 52/2017
            // Article 60 — without a transmitted credit note, the
            // buyer cannot reverse the input VAT they previously
            // claimed and the supplier's output VAT figures stay
            // inflated on the next return. Gated by config; afterCommit
            // so the queue worker can't pick the job up before the row
            // is persisted.
            \App\Jobs\SubmitEInvoiceCreditNoteJob::dispatch($cn->id)->afterCommit();

            return $cn->fresh();
        });
    }

    /**
     * Mark a tax invoice as voided. Does NOT delete the row — voiding is
     * the legal way to cancel a tax invoice without breaking the audit
     * trail. The PDF is left in place so the original document remains
     * downloadable for the historical record.
     *
     * Most callers should issue a credit note instead of (or in addition
     * to) voiding — voiding alone is appropriate for "this invoice was
     * issued in error and is being replaced", a credit note is for
     * "this invoice was correct but the supply was reversed".
     */
    public function voidInvoice(TaxInvoice $invoice, string $reason, ?int $voidedBy = null): TaxInvoice
    {
        if ($invoice->isVoided()) {
            return $invoice;
        }

        $invoice->update([
            'status'      => TaxInvoice::STATUS_VOIDED,
            'voided_at'   => now(),
            'voided_by'   => $voidedBy,
            'void_reason' => $reason,
        ]);

        return $invoice->fresh();
    }

    /**
     * Build the canonical line-item array for a payment.
     *
     * Phase 1 implementation: a single line per payment, derived from the
     * underlying contract's amounts JSON. Phase 2 will expand this to
     * pull the bid items so each line on the invoice mirrors a line on
     * the original PR/RFQ.
     *
     * @return list<array<string, mixed>>
     */
    private function buildLineItemsForPayment(Payment $payment, ?Contract $contract): array
    {
        $taxRate = (float) ($payment->vat_rate ?? 0);
        $taxableAmount = (float) $payment->amount;
        $taxAmount = (float) $payment->vat_amount;
        $lineTotal = (float) $payment->total_amount;

        $description = $contract?->title
            ?? trim(__('tax_invoices.line_milestone', [
                'milestone' => $payment->milestone ?? __('tax_invoices.line_default'),
            ]));

        return [
            [
                'description'    => $description,
                'quantity'       => 1,
                'unit'           => __('tax_invoices.unit_lump_sum'),
                'unit_price'     => $taxableAmount,
                'discount'       => 0,
                'taxable_amount' => $taxableAmount,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'line_total'     => $lineTotal,
            ],
        ];
    }

    /**
     * Sum line items into (subtotal, tax, total) tuple.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{0: float, 1: float, 2: float}
     */
    private function totalsFromLineItems(array $lines): array
    {
        $subtotal = 0.0;
        $tax      = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) ($line['taxable_amount'] ?? 0);
            $tax      += (float) ($line['tax_amount'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $tax      = round($tax, 2);
        $total    = round($subtotal + $tax, 2);
        return [$subtotal, $tax, $total];
    }

    private function formatAddress(Company $company): ?string
    {
        return collect([$company->address, $company->city, $company->country])
            ->filter()
            ->implode(', ') ?: null;
    }

    /**
     * Phase 1.5 — pull the VAT case off the contract envelope and map
     * it to a TaxInvoice::VAT_* constant. The contract was built by
     * ContractService::buildBilingualUaeContractTerms() which stamps
     * `vat_case` into the JSON envelope when the parties + their
     * Free Zone status are known. Legacy contracts don't carry it,
     * so we default to standard 5% (which is what the platform was
     * always doing pre-Phase-3 anyway).
     */
    private function resolveVatTreatment(?Contract $contract): string
    {
        if (!$contract || empty($contract->terms)) {
            return TaxInvoice::VAT_STANDARD;
        }

        $envelope = is_string($contract->terms)
            ? json_decode($contract->terms, true)
            : (array) $contract->terms;

        $case = $envelope['vat_case'] ?? null;
        return match ($case) {
            'reverse_charge'           => TaxInvoice::VAT_REVERSE_CHARGE,
            'designated_zone_internal' => TaxInvoice::VAT_DESIGNATED_ZONE_INTERNAL,
            'exempt'                   => TaxInvoice::VAT_EXEMPT,
            'zero_rated'               => TaxInvoice::VAT_ZERO_RATED,
            'out_of_scope'             => TaxInvoice::VAT_OUT_OF_SCOPE,
            default                    => TaxInvoice::VAT_STANDARD,
        };
    }

    /**
     * Render the invoice as a PDF, persist it on the local disk, and
     * stamp the row with the path + sha256.
     */
    public function renderAndStorePdf(TaxInvoice $invoice): TaxInvoice
    {
        $invoice->refresh();

        // Phase 7 (UAE Compliance Roadmap) — Corporate Tax annotation.
        // Pull the supplier's CT status so the PDF can show "QFZP"
        // or "exempt" if applicable. Loaded from the snapshot company_id.
        $supplierCompany = \App\Models\Company::find($invoice->supplier_company_id);
        $ctAnnotation = $supplierCompany?->ctAnnotation();

        $pdf = Pdf::loadView('dashboard.admin.tax-invoices.pdf', [
            'invoice'      => $invoice,
            'qrDataUri'    => $this->qrEncoder->renderDataUri($invoice),
            'ctAnnotation' => $ctAnnotation,
        ])->setPaper('a4');

        $bytes = $pdf->output();
        $sha   = hash('sha256', $bytes);

        $path = sprintf('tax-invoices/%s.pdf', $invoice->invoice_number);
        Storage::disk('local')->put($path, $bytes);

        $invoice->update([
            'pdf_path'   => $path,
            'pdf_sha256' => $sha,
        ]);

        return $invoice->fresh();
    }

    public function renderAndStoreCreditNotePdf(TaxCreditNote $cn): TaxCreditNote
    {
        $cn->refresh();

        $pdf = Pdf::loadView('dashboard.admin.tax-invoices.credit-note-pdf', ['creditNote' => $cn])
            ->setPaper('a4');

        $bytes = $pdf->output();
        $sha   = hash('sha256', $bytes);

        $path = sprintf('tax-credit-notes/%s.pdf', $cn->credit_note_number);
        Storage::disk('local')->put($path, $bytes);

        $cn->update([
            'pdf_path'   => $path,
            'pdf_sha256' => $sha,
        ]);

        return $cn->fresh();
    }
}
