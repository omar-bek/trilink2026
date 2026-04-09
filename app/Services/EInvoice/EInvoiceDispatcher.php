<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;
use App\Models\TaxInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
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
            'asp_provider'    => $providerKey,
            'asp_environment' => (string) config('einvoice.environment', 'sandbox'),
            'status'          => EInvoiceSubmission::STATUS_QUEUED,
            'payload_xml'     => $payload,
            'payload_sha256'  => $sha,
            'submitted_at'    => null,
        ]);

        return $provider->submit($submission);
    }

    /**
     * Re-attempt a previously failed submission. Used by the admin
     * "Retry" button + the queue worker that watches `next_retry_at`.
     * Bumps the retries counter and rebuilds the payload from the
     * current TaxInvoice state — if the invoice was voided in the
     * meantime, that change is reflected in the new attempt.
     */
    public function retry(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        if (!$this->isEnabled()) {
            return $submission;
        }

        $invoice = $submission->taxInvoice;
        if (!$invoice) {
            $submission->update([
                'status'        => EInvoiceSubmission::STATUS_REJECTED,
                'error_message' => 'Tax invoice no longer exists.',
            ]);
            return $submission->fresh();
        }

        $provider = $this->resolveProvider($submission->asp_provider);

        $payload = $this->mapper->toUbl($invoice);
        $sha     = hash('sha256', $payload);

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

        return $submission->fresh();
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
}
