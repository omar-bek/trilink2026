<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;
use Carbon\CarbonImmutable;

/**
 * Phase 5 (UAE Compliance Roadmap) — local-only ASP provider used for
 * tests, demos, and the period before a real ASP contract is signed.
 *
 * Generates a deterministic fake ASP submission id + a deterministic
 * fake FTA clearance id for the submission so the rest of the
 * pipeline (admin queue, webhook test harness, audit log) can be
 * exercised end-to-end without hitting an external service.
 *
 * The XML payload is REAL — it's the same UBL 2.1 PINT-AE document
 * the production providers will transmit. The only thing that's faked
 * is the network call. Schema-validating the XML against the official
 * PINT-AE xsd is the test suite's responsibility.
 *
 * Behaviour:
 *
 *   - submit() flips the row from queued → submitted → accepted in a
 *     single call (no async). Sets fta_clearance_id to a deterministic
 *     hash of the payload so re-submitting an identical payload
 *     produces an identical clearance id (useful for idempotency
 *     tests).
 *
 *   - fetchStatus() is a no-op — the row is already final after
 *     submit() returns.
 */
class MockAspProvider implements AspProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function submit(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        $now = CarbonImmutable::now();

        $submissionId = 'MOCK-' . substr(hash('sha256', (string) $submission->id . $now->toIso8601String()), 0, 16);
        $clearanceId  = 'FTA-MOCK-' . substr((string) $submission->payload_sha256, 0, 24);

        $submission->update([
            'status'                => EInvoiceSubmission::STATUS_ACCEPTED,
            'asp_submission_id'     => $submissionId,
            'asp_acknowledgment_id' => $submissionId,
            'fta_clearance_id'      => $clearanceId,
            'submitted_at'          => $now,
            'acknowledged_at'       => $now,
            'asp_response_raw'      => [
                'provider'      => 'mock',
                'submission_id' => $submissionId,
                'clearance_id'  => $clearanceId,
                'received_at'   => $now->toIso8601String(),
                'environment'   => $submission->asp_environment,
                'note'          => 'Local mock provider — no external transmission. Replace with a real ASP before FTA Phase 1 go-live.',
            ],
            'next_retry_at'         => null,
            'error_message'         => null,
        ]);

        return $submission->fresh();
    }

    public function fetchStatus(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        // Mock provider is synchronous — submit() already left the row
        // in its terminal state. Returning unchanged is correct.
        return $submission;
    }
}
