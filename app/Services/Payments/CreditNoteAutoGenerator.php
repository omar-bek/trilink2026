<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use App\Services\Tax\InvoiceNumberAllocator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates a TaxCreditNote whenever a payment is refunded or partially
 * reversed. UAE Cabinet Decision 52/2017 Article 60 requires a credit
 * note before the buyer can reverse input tax already claimed — without
 * one we leave both parties stuck.
 *
 * Scope here is deliberately narrow: this service only produces the
 * MODEL (with line items, totals, proper CN-YYYY-###### number). The
 * PDF render + Peppol submission pipeline is handled by existing
 * TaxInvoice/EInvoice services once the CN model exists.
 */
class CreditNoteAutoGenerator
{
    public function __construct(private readonly InvoiceNumberAllocator $numbers) {}

    public function generateFromRefund(
        Payment $payment,
        float $refundAmount,
        string $reason = TaxCreditNote::REASON_REFUND,
        ?int $issuedBy = null,
    ): ?TaxCreditNote {
        if ($refundAmount <= 0) {
            return null;
        }

        $invoice = TaxInvoice::query()->where('payment_id', $payment->id)->first();
        if (! $invoice) {
            // No invoice yet = nothing to credit. Happens when a payment
            // is refunded before the tax invoice is issued (e.g. deposit
            // returned immediately after AML hit). Caller logs.
            return null;
        }

        // Idempotency: if a CN was already cut for this payment + reason,
        // return the existing row rather than double-issuing.
        $existing = TaxCreditNote::query()
            ->where('original_invoice_id', $invoice->id)
            ->where('reason', $reason)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($invoice, $payment, $refundAmount, $reason, $issuedBy) {
            $supplierCompanyId = (int) $invoice->supplier_company_id;
            $cnNumber = $this->numbers->allocate($supplierCompanyId, InvoiceNumberAllocator::SERIES_CREDIT_NOTE);

            $ratio = (float) $invoice->total_inclusive > 0
                ? min(1.0, $refundAmount / (float) $invoice->total_inclusive)
                : 1.0;

            $subtotal = round((float) $invoice->subtotal_excl_tax * $ratio, 2);
            $tax = round((float) $invoice->total_tax * $ratio, 2);
            $total = round($subtotal + $tax, 2);

            $items = is_array($invoice->line_items ?? null) ? $invoice->line_items : [];
            foreach ($items as &$li) {
                foreach (['amount', 'tax_amount', 'total'] as $k) {
                    if (isset($li[$k])) {
                        $li[$k] = round((float) $li[$k] * $ratio, 2);
                    }
                }
                $li['is_credit_note'] = true;
            }
            unset($li);

            $cn = TaxCreditNote::create([
                'credit_note_number' => $cnNumber,
                'original_invoice_id' => $invoice->id,
                'issue_date' => now()->toDateString(),
                'reason' => $reason,
                'notes' => 'Auto-generated from refund of payment #'.$payment->id,
                'line_items' => $items,
                'subtotal_excl_tax' => $subtotal,
                'total_tax' => $tax,
                'total_inclusive' => $total,
                'currency' => $invoice->currency,
                'issued_by' => $issuedBy,
                'issued_at' => now(),
                'metadata' => [
                    'refund_amount' => $refundAmount,
                    'ratio' => $ratio,
                    'payment_id' => $payment->id,
                ],
            ]);

            $payment->update(['refund_credit_note_id' => $cn->id]);

            return $cn;
        });
    }
}
