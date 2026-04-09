<?php

namespace App\Services\Signing;

use RuntimeException;

/**
 * Phase 6 (UAE Compliance Roadmap) — ESSP TrustSign API skeleton.
 *
 * Same shape as {@see ComtrustTspProvider}: a real class so the
 * config registry has something to bind to, but the actual API
 * integration is deferred until the commercial contract with ESSP
 * lands. See the Comtrust docblock for the rationale.
 *
 * Configure with:
 *   SIGNING_ESSP_ENABLED=true
 *   SIGNING_ESSP_API_KEY=...
 *   SIGNING_ESSP_BASE_URL=https://api.essp.ae/v1
 *
 * ESSP is TDRA-accredited under Federal Decree-Law 46/2021 so its
 * certificates produce a Qualified-grade signature in court.
 */
class EsspTspProvider implements TrustServiceProviderInterface
{
    public function name(): string
    {
        return 'essp';
    }

    public function signHash(string $contractHash, array $signerContext): array
    {
        $config = (array) config('signing.tsp_providers.essp', []);
        if (empty($config['enabled']) || empty($config['api_key'])) {
            throw new RuntimeException(
                'ESSP TSP not configured. Set SIGNING_ESSP_ENABLED=true and SIGNING_ESSP_API_KEY before enabling.'
            );
        }

        throw new RuntimeException('EsspTspProvider::signHash() is not yet implemented. Configure provider before enabling.');
    }

    public function verify(string $contractHash, array $envelope): array
    {
        return [
            'valid'        => false,
            'reason'       => 'ESSP verifier not implemented in skeleton.',
            'signed_at'    => $envelope['signed_at'] ?? null,
            'tsp_provider' => 'essp',
            'cert_chain'   => [],
        ];
    }
}
