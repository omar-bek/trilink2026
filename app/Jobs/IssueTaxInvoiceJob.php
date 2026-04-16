<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Tax\TaxInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Background job that issues a tax invoice for a completed Payment.
 *
 * Triggered by the PaymentObserver when a payment row transitions from
 * any state into PaymentStatus::COMPLETED. Runs out of band so the
 * webhook handler / admin click that completed the payment doesn't have
 * to wait for PDF generation + sequence allocation.
 *
 * Idempotency:
 *
 *   - ShouldBeUnique with the payment id as the lock key. If the same
 *     job is dispatched twice (Stripe webhook retry, manual admin click,
 *     escrow auto-release all firing for the same payment) only one
 *     instance enters the queue.
 *   - The TaxInvoiceService::issueFor() call is itself idempotent — if
 *     a non-voided invoice already exists for this payment, it just
 *     returns it without spending a new sequence number.
 *
 * Failure mode:
 *
 *   - Up to 3 attempts with 30s exponential backoff. Most failures are
 *     "PDF render hit a missing translation key" or "DB lock timeout"
 *     which both clear on retry.
 *   - On final failure the exception bubbles up and Laravel marks the
 *     job as failed in the failed_jobs table; an admin can re-trigger
 *     manually from the finance UI ("Re-issue invoice" button).
 */
class IssueTaxInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $paymentId,
        public readonly ?int $issuedBy = null,
    ) {
        // Pin to a dedicated queue so PDF rendering doesn't compete with
        // notifications / sanctions screening / digests.
        $this->onQueue('invoices');
    }

    public function uniqueId(): string
    {
        return 'issue-tax-invoice:'.$this->paymentId;
    }

    public function handle(TaxInvoiceService $service): void
    {
        $payment = Payment::find($this->paymentId);

        if (! $payment) {
            // Payment was deleted between dispatch and processing — drop
            // the job silently rather than failing it.
            return;
        }

        if ($payment->status !== PaymentStatus::COMPLETED) {
            // Payment was reverted (e.g. status flip back to FAILED via
            // a webhook race). Don't issue an invoice for a non-completed
            // payment — just exit.
            return;
        }

        $service->issueFor($payment, $this->issuedBy);
    }

    public function failed(Throwable $exception): void
    {
        // Hook for Phase 8: notify finance admins that auto-issuance
        // failed and a manual re-issue is needed. For now, the failed
        // jobs table is the source of truth.
        report($exception);
    }
}
