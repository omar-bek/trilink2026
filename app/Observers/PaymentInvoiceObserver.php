<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Jobs\IssueTaxInvoiceJob;
use App\Models\Payment;
use App\Services\FtaTaxRoutingService;

/**
 * Auto-issue tax invoices when a Payment becomes COMPLETED.
 *
 * Phase 1 of the UAE Compliance Roadmap. The reason this lives in an
 * observer instead of inside PaymentService::process() is that the
 * payment status flips to COMPLETED from MANY code paths, not just
 * PaymentService:
 *
 *   - WebhookController::handleStripeSuccess  (Stripe payment_intent.succeeded)
 *   - WebhookController::paypalWebhook        (PayPal capture.completed)
 *   - EscrowService::release                  (escrow milestone release)
 *   - Manual admin update from finance UI
 *   - Backfill scripts during data import
 *
 * The observer pattern catches every one of those without needing each
 * caller to know about the invoice pipeline.
 *
 * Idempotency:
 *
 *   - We dispatch IssueTaxInvoiceJob, which is ShouldBeUnique. Two
 *     parallel updates that flip the same payment to COMPLETED won't
 *     both end up issuing two invoices.
 *   - The job's handler also rechecks the payment status before
 *     spending a sequence number.
 */
class PaymentInvoiceObserver
{
    public function updated(Payment $payment): void
    {
        // Only act on transitions INTO completed. A payment that was
        // already completed and is being touched for some other column
        // (notes, escrow link, etc.) shouldn't re-issue.
        if (! $payment->wasChanged('status')) {
            return;
        }

        $newStatus = $payment->status;
        if ($newStatus !== PaymentStatus::COMPLETED) {
            return;
        }

        // Defensive: an old status that was already "completed" can't
        // become "completed" again. wasChanged returns true on every
        // explicit update so we double-check the original.
        $original = $payment->getOriginal('status');
        if ($original === PaymentStatus::COMPLETED || $original === 'completed') {
            return;
        }

        IssueTaxInvoiceJob::dispatch($payment->id);

        // FTA auto-routing — accrue VAT / Corporate Tax / WHT against
        // the tax ledger and kick the routing step so funds end up in
        // the company's ring-fenced tax account without finance having
        // to remember to move them. Fires synchronously so the ledger
        // row is visible the moment the payment UI refreshes.
        try {
            /** @var FtaTaxRoutingService $router */
            $router = app(FtaTaxRoutingService::class);
            foreach ($router->accrueFor($payment) as $entry) {
                $router->route($entry);
            }
        } catch (\Throwable $e) {
            // Tax routing failing must never roll back the payment
            // itself — log and let the nightly reconciliation job pick
            // up any stuck rows.
            report($e);
        }
    }
}
