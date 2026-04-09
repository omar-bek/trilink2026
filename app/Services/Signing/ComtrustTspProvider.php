<?php

namespace App\Services\Signing;

use RuntimeException;

/**
 * Phase 6 (UAE Compliance Roadmap) — Comtrust e-Sign API skeleton.
 *
 * Real integration is intentionally NOT implemented. This class
 * exists so:
 *
 *   1. The configuration registry in config/signing.php has a real
 *      class to bind to when SIGNING_TSP_PROVIDER=comtrust is set.
 *   2. Anyone evaluating provider lock-in can see what wiring would
 *      look like when the real TSP contract is signed — only this
 *      file plus the config row need to change.
 *   3. The dispatcher can resolve and call the class without crashing
 *      when an admin accidentally flips the provider switch in
 *      production before credentials land — instead of a fatal it
 *      throws a clear, actionable error.
 *
 * Configure with:
 *   SIGNING_COMTRUST_ENABLED=true
 *   SIGNING_COMTRUST_API_KEY=...
 *   SIGNING_COMTRUST_BASE_URL=https://api.comtrust.ae/v1
 *
 * Comtrust is TDRA-accredited under Federal Decree-Law 46/2021 so
 * its certificates produce a Qualified-grade signature in court.
 */
class ComtrustTspProvider implements TrustServiceProviderInterface
{
    public function name(): string
    {
        return 'comtrust';
    }

    public function signHash(string $contractHash, array $signerContext): array
    {
        $config = (array) config('signing.tsp_providers.comtrust', []);
        if (empty($config['enabled']) || empty($config['api_key'])) {
            throw new RuntimeException(
                'Comtrust TSP not configured. Set SIGNING_COMTRUST_ENABLED=true and SIGNING_COMTRUST_API_KEY before enabling.'
            );
        }

        // Real implementation lands here when the contract is signed:
        //   1. POST $config['base_url'] . '/sign' with the hash + signer
        //   2. Receive a CAdES envelope + RFC 3161 timestamp token
        //   3. Stamp the envelope shape from the interface docblock
        throw new RuntimeException('ComtrustTspProvider::signHash() is not yet implemented. Configure provider before enabling.');
    }

    public function verify(string $contractHash, array $envelope): array
    {
        // Verification can be done OFFLINE if the cert chain is in
        // the envelope — we don't strictly need the live API for
        // verification — but for now we defer until the real signer
        // is wired up.
        return [
            'valid'        => false,
            'reason'       => 'Comtrust verifier not implemented in skeleton.',
            'signed_at'    => $envelope['signed_at'] ?? null,
            'tsp_provider' => 'comtrust',
            'cert_chain'   => [],
        ];
    }
}
