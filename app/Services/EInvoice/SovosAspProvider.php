<?php

namespace App\Services\EInvoice;

use App\Models\EInvoiceSubmission;
use RuntimeException;

/**
 * Phase 5 (UAE Compliance Roadmap) — Sovos eInvoicing skeleton.
 *
 * Same shape as {@see AvalaraAspProvider}: a real class so the config
 * registry has something to bind to, but the actual API integration
 * is deferred until the commercial contract with Sovos lands. See the
 * Avalara docblock for the rationale.
 *
 * Configure with:
 *   EINVOICE_SOVOS_ENABLED=true
 *   EINVOICE_SOVOS_API_KEY=...
 *   EINVOICE_SOVOS_BASE_URL=https://api.sandbox.sovos.com/einvoicing
 */
class SovosAspProvider implements AspProviderInterface
{
    public function name(): string
    {
        return 'sovos';
    }

    public function submit(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        $config = (array) config('einvoice.providers.sovos', []);
        if (empty($config['enabled']) || empty($config['api_key'])) {
            $submission->update([
                'status' => EInvoiceSubmission::STATUS_FAILED,
                'error_message' => 'Sovos provider not configured. Set EINVOICE_SOVOS_ENABLED=true and EINVOICE_SOVOS_API_KEY before enabling.',
                'submitted_at' => now(),
            ]);

            return $submission->fresh();
        }

        throw new RuntimeException('SovosAspProvider::submit() is not yet implemented. Configure provider before enabling.');
    }

    public function fetchStatus(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        throw new RuntimeException('SovosAspProvider::fetchStatus() is not yet implemented.');
    }
}
