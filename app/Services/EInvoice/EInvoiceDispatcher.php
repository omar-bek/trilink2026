<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;
use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Notifications\EInvoiceAcceptedNotification;
use App\Notifications\EInvoiceDispatchedNotification;
use App\Notifications\EInvoiceRejectedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Phase 5 (UAE Compliance Roadmap) — orchestration layer between the
 * TaxInvoiceService (which produces invoices), the PintAeMapper (which
 * builds UBL XML), and whichever AspProviderInterface implementation
 * is currently configured.
 *
 * The dispatcher is the single entry point everything else calls. It:
 *
 *   1. Decides which provider to use (config('einvoice.default_provider')
 *      with optional per-tenant override).
 *   2. Resolves the provider class via the container.
 *   3. Creates an EInvoiceSubmission row in the `queued` state, stamps
 *      the UBL payload + sha256 onto it.
 *   4. Calls $provider->submit() which moves the row to its terminal
 *      state.
 *   5. Returns the row.
 *
 * The dispatcher is config-gated. When `einvoice.enabled` is false it
 * returns null and writes nothing to the database — that lets the
 * TaxInvoiceService keep its existing flow without an `if` ladder.
 */
class EInvoiceDispatcher
{
    public function __construct(
        private readonly PintAeMapper $mapper,
    ) {
    }

    /**
     * Build the payload, persist a submission row, hand it to the
     * provider. Returns the row in its post-submit state, or null
     * when e-invoicing is disabled platform-wide.
     */
    public function dispatchFor(TaxInvoice $invoice): ?EInvoiceSubmission
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $providerKey = (string) config('einvoice.default_provider', 'mock');
        $provider    = $this->resolveProvider($providerKey);

        $payload = $this->mapper->toUbl($invoice);
        $sha     = hash('sha256', $payload);

        $submission = EInvoiceSubmission::create([
            'tax_invoice_id'  => $invoice->id,
            // Phase 5.5 — explicit document_type stamp so the retry +
            // webhook flows can discriminate without re-deriving from
            // tax_invoice_id IS NOT NULL.
            'document_type'   => EInvoiceSubmission::DOC_INVOICE,
            'asp_provider'    => $providerKey,
            'asp_environment' => (string) config('einvoice.environment', 'sandbox'),
            'status'          => EInvoiceSubmission::STATUS_QUEUED,
            'payload_xml'     => $payload,
            'payload_sha256'  => $sha,
            'submitted_at'    => null,
        ]);

        $result = $provider->submit($submission);
        $this->notifyDispatched($result ?? $submission);

        return $result;
    }

    /**
     * Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening)
     * — credit note variant of dispatchFor(). Tax credit notes need
     * exactly the same Peppol clearance flow as tax invoices, just
     * with a different document type code (381 vs 388) and a
     * BillingReference back to the original invoice. See
     * PintAeMapper::toCreditNoteUbl() for the XML differences.
     *
     * Without this method, refunds + dispute settlements never reach
     * the FTA — buyers can't reverse the input VAT they previously
     * claimed and the supplier's output VAT figures stay inflated on
     * the next return. That's a Cabinet Decision 52/2017 Article 60
     * violation.
     */
    public function dispatchForCreditNote(TaxCreditNote $creditNote): ?EInvoiceSubmission
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $providerKey = (string) config('einvoice.default_provider', 'mock');
        $provider    = $this->resolveProvider($providerKey);

        $payload = $this->mapper->toCreditNoteUbl($creditNote);
        $sha     = hash('sha256', $payload);

        $submission = EInvoiceSubmission::create([
            'tax_credit_note_id' => $creditNote->id,
            'document_type'      => EInvoiceSubmission::DOC_CREDIT_NOTE,
            'asp_provider'       => $providerKey,
            'asp_environment'    => (string) config('einvoice.environment', 'sandbox'),
            'status'             => EInvoiceSubmission::STATUS_QUEUED,
            'payload_xml'        => $payload,
            'payload_sha256'     => $sha,
            'submitted_at'       => null,
        ]);

        $result = $provider->submit($submission);
        $this->notifyDispatched($result ?? $submission);

        return $result;
    }

    /**
     * Re-attempt a previously failed submission. Used by the admin
     * "Retry" button + the queue worker that watches `next_retry_at`.
     * Bumps the retries counter and rebuilds the payload from the
     * current TaxInvoice (or TaxCreditNote) state — if the underlying
     * document was voided in the meantime, that change is reflected
     * in the new attempt.
     */
    public function retry(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        if (!$this->isEnabled()) {
            return $submission;
        }

        // Phase 5.5 — retry handles BOTH document types. Pick the
        // right mapper + payload source based on what the submission
        // points at. The shape of the EInvoiceSubmission row is
        // identical between invoices and credit notes; only the
        // payload generation differs.
        if ($submission->isCreditNote()) {
            $creditNote = $submission->taxCreditNote;
            if (!$creditNote) {
                $submission->update([
                    'status'        => EInvoiceSubmission::STATUS_REJECTED,
                    'error_message' => 'Tax credit note no longer exists.',
                ]);
                return $submission->fresh();
            }
            $payload = $this->mapper->toCreditNoteUbl($creditNote);
        } else {
            $invoice = $submission->taxInvoice;
            if (!$invoice) {
                $submission->update([
                    'status'        => EInvoiceSubmission::STATUS_REJECTED,
                    'error_message' => 'Tax invoice no longer exists.',
                ]);
                return $submission->fresh();
            }
            $payload = $this->mapper->toUbl($invoice);
        }

        $provider = $this->resolveProvider($submission->asp_provider);
        $sha      = hash('sha256', $payload);

        $submission->update([
            'status'         => EInvoiceSubmission::STATUS_QUEUED,
            'payload_xml'    => $payload,
            'payload_sha256' => $sha,
            'retries'        => $submission->retries + 1,
            'next_retry_at'  => null,
            'error_message'  => null,
        ]);

        return $provider->submit($submission->fresh());
    }

    /**
     * Mark a submission as accepted/rejected via an async webhook
     * callback. The webhook controller validates the signature, then
     * hands off here so all status mutations happen in one place.
     */
    public function ackFromWebhook(EInvoiceSubmission $submission, array $payload): EInvoiceSubmission
    {
        $accepted = ($payload['status'] ?? '') === 'accepted';

        $submission->update([
            'status'              => $accepted
                ? EInvoiceSubmission::STATUS_ACCEPTED
                : EInvoiceSubmission::STATUS_REJECTED,
            'fta_clearance_id'    => $payload['clearance_id'] ?? $submission->fta_clearance_id,
            'asp_acknowledgment_id' => $payload['acknowledgment_id'] ?? $submission->asp_acknowledgment_id,
            'asp_response_raw'    => array_merge((array) $submission->asp_response_raw, $payload),
            'acknowledged_at'     => CarbonImmutable::now(),
            'error_message'       => $accepted ? null : ($payload['error'] ?? 'Rejected by FTA'),
        ]);

        $fresh = $submission->fresh();
        if ($fresh) {
            $this->notifyAckOutcome($fresh, $accepted);
        }

        return $fresh ?? $submission;
    }

    public function isEnabled(): bool
    {
        return (bool) config('einvoice.enabled', false);
    }

    /**
     * Resolve the provider class from config. Throws when the slug is
     * unknown so a typo in the env var fails fast and loud instead of
     * silently dropping submissions.
     */
    private function resolveProvider(string $key): AspProviderInterface
    {
        $registry = (array) config('einvoice.providers', []);
        if (!isset($registry[$key]['class'])) {
            throw new RuntimeException("Unknown e-invoice provider: {$key}");
        }
        return App::make($registry[$key]['class']);
    }

    /**
     * Resolve every active user belonging to the company that issued
     * the underlying invoice/credit note. Used by both notification
     * fan-outs below — kept central so a future "tax_recipients_only"
     * filter only needs to change one place.
     */
    private function resolveSupplierRecipients(EInvoiceSubmission $submission): \Illuminate\Database\Eloquent\Collection
    {
        $companyId = $submission->taxInvoice?->seller_company_id
            ?? $submission->taxCreditNote?->seller_company_id
            ?? null;

        if (!$companyId) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return User::where('company_id', $companyId)->get();
    }

    /**
     * Fan out the "dispatched to FTA" confirmation. Wrapped in a
     * try/catch so a notification failure cannot block the underlying
     * dispatch — losing a notification is recoverable, losing the
     * tax submission isn't.
     */
    private function notifyDispatched(EInvoiceSubmission $submission): void
    {
        try {
            $recipients = $this->resolveSupplierRecipients($submission);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new EInvoiceDispatchedNotification($submission));
            }
        } catch (\Throwable $e) {
            \Log::warning('EInvoiceDispatcher::notifyDispatched failed', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fan out the appropriate webhook ack notification — accepted
     * fires the success class, rejected fires the error class with
     * the FTA's error message attached.
     */
    private function notifyAckOutcome(EInvoiceSubmission $submission, bool $accepted): void
    {
        try {
            $recipients = $this->resolveSupplierRecipients($submission);
            if ($recipients->isEmpty()) {
                return;
            }

            $notification = $accepted
                ? new EInvoiceAcceptedNotification($submission)
                : new EInvoiceRejectedNotification($submission);

            Notification::send($recipients, $notification);
        } catch (\Throwable $e) {
            \Log::warning('EInvoiceDispatcher::notifyAckOutcome failed', [
                'submission_id' => $submission->id,
                'accepted'      => $accepted,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
