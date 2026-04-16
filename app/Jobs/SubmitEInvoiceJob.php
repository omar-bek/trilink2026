<?php

namespace App\Jobs;

use App\Models\TaxInvoice;
use App\Services\EInvoice\EInvoiceDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Phase 5 (UAE Compliance Roadmap) — async wrapper around
 * EInvoiceDispatcher::dispatchFor().
 *
 * Triggered automatically by TaxInvoiceService::issueFor() the moment
 * a tax invoice is minted. The job runs on a dedicated queue so PDF
 * rendering, sanctions screening and digests don't compete with it.
 *
 * Idempotency:
 *
 *   - ShouldBeUnique on the tax_invoice_id. Two parallel issuances
 *     (which shouldn't happen — the issuance pipeline is itself
 *     uniqued — but defensive) collapse to a single submission.
 *   - The dispatcher creates ONE EInvoiceSubmission row per call;
 *     the row's lifecycle is the source of truth for retries.
 *
 * Failure mode:
 *
 *   - 3 attempts with exponential backoff. The provider implementation
 *     never throws on transient failures (see AspProviderInterface
 *     contract) — exceptions only happen on programming errors, in
 *     which case Laravel's failed_jobs table catches them.
 */
class SubmitEInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $taxInvoiceId,
    ) {
        $this->onQueue('einvoice');
    }

    public function uniqueId(): string
    {
        return 'einvoice-submit:'.$this->taxInvoiceId;
    }

    public function handle(EInvoiceDispatcher $dispatcher): void
    {
        if (! $dispatcher->isEnabled()) {
            return;
        }

        $invoice = TaxInvoice::find($this->taxInvoiceId);
        if (! $invoice) {
            // Tax invoice was hard-deleted between dispatch and run.
            // Drop silently — there's nothing left to transmit.
            return;
        }

        $dispatcher->dispatchFor($invoice);
    }

    public function failed(Throwable $exception): void
    {
        // Hook for ops alerting in Phase 8. For Phase 5 the failed_jobs
        // table is enough — admins can re-trigger via the e-invoice
        // queue UI without going to the CLI.
        report($exception);
    }
}
