<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;
use RuntimeException;

/**
 * Phase 5 (UAE Compliance Roadmap) — Avalara E-Invoicing API skeleton.
 *
 * Real integration is intentionally NOT implemented. This class
 * exists so:
 *
 *   1. The configuration registry in config/einvoice.php has a real
 *      class to bind to when EINVOICE_PROVIDER=avalara is set.
 *   2. Anyone evaluating provider lock-in can see what wiring would
 *      look like when the real ASP contract is signed — only this
 *      file plus the config row need to change.
 *   3. The dispatcher can resolve and call the class without crashing
 *      when an admin accidentally flips the provider switch in
 *      production before credentials land — instead of a fatal it
 *      stamps a clear, actionable error on the submission row.
 *
 * Configure with:
 *   EINVOICE_AVALARA_ENABLED=true
 *   EINVOICE_AVALARA_API_KEY=...
 *   EINVOICE_AVALARA_BASE_URL=https://api.sbx.avalara.com/einvoicing/v1
 */
class AvalaraAspProvider implements AspProviderInterface
{
    public function name(): string
    {
        return 'avalara';
    }

    public function submit(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        $config = (array) config('einvoice.providers.avalara', []);
        if (empty($config['enabled']) || empty($config['api_key'])) {
            $submission->update([
                'status'        => EInvoiceSubmission::STATUS_FAILED,
                'error_message' => 'Avalara provider not configured. Set EINVOICE_AVALARA_ENABLED=true and EINVOICE_AVALARA_API_KEY before enabling.',
                'submitted_at'  => now(),
            ]);
            return $submission->fresh();
        }

        // Real implementation lands here when the contract is signed.
        // Outline:
        //   1. POST $config['base_url'] . '/documents'
        //      with the UBL XML in the body and Bearer auth.
        //   2. On 200/201, parse the response, stamp asp_submission_id
        //      + status=submitted, leave clearance for the webhook.
        //   3. On 4xx, mark rejected with the body as error_message.
        //   4. On 5xx / network, mark failed + schedule next_retry_at.
        throw new RuntimeException('AvalaraAspProvider::submit() is not yet implemented. Configure provider before enabling.');
    }

    public function fetchStatus(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        throw new RuntimeException('AvalaraAspProvider::fetchStatus() is not yet implemented.');
    }
}
