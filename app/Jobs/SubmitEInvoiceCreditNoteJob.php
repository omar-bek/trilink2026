<?php

namespace App\Jobs;

use App\Models\EInvoiceSubmission;
use App\Models\TaxCreditNote;
use App\Services\EInvoice\EInvoiceDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening)
 * — async wrapper around EInvoiceDispatcher::dispatchForCreditNote().
 *
 * Sibling of {@see SubmitEInvoiceJob} but for credit notes. Kept as a
 * separate class instead of overloading the invoice job because:
 *
 *   - Distinct unique-key namespace (`einvoice-cn:` vs `einvoice-submit:`)
 *     so a parallel cn + invoice for the same source don't collide.
 *   - Cleaner audit trail in the failed_jobs table — operators can
 *     filter by class to see only credit-note failures.
 *   - The two flows may diverge in retry / backoff policy as we learn
 *     more about how the real ASP behaves on credit notes.
 *
 * Triggered by TaxInvoiceService::issueCreditNote() the moment a
 * credit note is minted, gated by config('einvoice.enabled').
 */
class SubmitEInvoiceCreditNoteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $taxCreditNoteId,
    ) {
        $this->onQueue('einvoice');
    }

    public function uniqueId(): string
    {
        return 'einvoice-cn:'.$this->taxCreditNoteId;
    }

    public function handle(EInvoiceDispatcher $dispatcher): void
    {
        if (! $dispatcher->isEnabled()) {
            return;
        }

        $creditNote = TaxCreditNote::find($this->taxCreditNoteId);
        if (! $creditNote) {
            // Loud failure: a credit note that was enqueued but is now
            // missing means either (a) it was deleted between enqueue
            // and execution, or (b) the enqueue site passed an invalid
            // id. Either way the FTA never receives a corresponding
            // credit note, revenue is left unreconciled, and there must
            // be a recoverable trail. Log + persist a failed submission
            // row so the operator dashboard surfaces it.
            Log::warning('SubmitEInvoiceCreditNoteJob: credit note vanished before submission', [
                'tax_credit_note_id' => $this->taxCreditNoteId,
            ]);

            try {
                EInvoiceSubmission::create([
                    'tax_credit_note_id' => $this->taxCreditNoteId,
                    'document_type' => 'credit_note',
                    'status' => 'failed',
                    'error_message' => 'TaxCreditNote row not found at job execution time. The credit note was either deleted or never persisted.',
                    'attempted_at' => now(),
                ]);
            } catch (Throwable $e) {
                // The model schema may not yet have all those columns —
                // log instead of crashing the job. The Log entry above
                // is the floor of the audit trail.
                Log::warning('SubmitEInvoiceCreditNoteJob: failed to persist failed_submission row', [
                    'tax_credit_note_id' => $this->taxCreditNoteId,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        $dispatcher->dispatchForCreditNote($creditNote);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
