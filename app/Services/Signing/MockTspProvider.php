<?php

namespace App\Services\Signing;

use Carbon\CarbonImmutable;

/**
 * Phase 6 (UAE Compliance Roadmap) — local-only TSP used for tests
 * and the period before a commercial Comtrust / ESSP contract is
 * signed. Produces a deterministic CAdES-style envelope from the
 * application key so the signing pipeline can be exercised end-to-end
 * without hitting an external service.
 *
 * Important: this provider is NOT cryptographically equivalent to a
 * real TSP. The "signature" is hash_hmac(APP_KEY) and any party that
 * knows APP_KEY can forge it. The mock exists so:
 *
 *   1. The rest of the pipeline (resolver → ContractService::sign →
 *      verify endpoint) can be exercised in tests + demos.
 *   2. The contract pipeline can prove "Qualified" signing works
 *      end-to-end before a commercial TSP contract lands.
 *
 * Production deployments MUST switch to a real provider before any
 * Qualified signature has legal weight. config('signing.default_tsp_provider')
 * is the gate.
 */
class MockTspProvider implements TrustServiceProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function signHash(string $contractHash, array $signerContext): array
    {
        $signedAt = CarbonImmutable::now();
        $appKey = (string) config('app.key');

        // Deterministic per (hash + signer + timestamp) so two parallel
        // calls produce different envelopes (avoids confusing test
        // assertions that pin the signature byte-for-byte across runs).
        $payload = hash_hmac(
            'sha256',
            implode('|', [
                $contractHash,
                $signerContext['user_id']    ?? '',
                $signerContext['company_id'] ?? '',
                $signedAt->toIso8601String(),
            ]),
            $appKey
        );

        $timestampToken = hash_hmac(
            'sha256',
            $payload . '|' . $signedAt->toIso8601String(),
            $appKey
        );

        return [
            'tsp_provider'       => 'mock',
            'tsp_certificate_id' => 'MOCK-CERT-' . substr(hash('sha256', $payload), 0, 16),
            'signature_format'   => 'CAdES',
            'signature_payload'  => base64_encode($payload),
            'timestamp_token'    => base64_encode($timestampToken),
            'signed_at'          => $signedAt->toIso8601String(),
        ];
    }

    public function verify(string $contractHash, array $envelope): array
    {
        $appKey = (string) config('app.key');
        $signedAt = (string) ($envelope['signed_at'] ?? '');

        $expectedPayload = hash_hmac(
            'sha256',
            implode('|', [
                $contractHash,
                $envelope['signer_user_id']    ?? '',
                $envelope['signer_company_id'] ?? '',
                $signedAt,
            ]),
            $appKey
        );

        $providedPayload = base64_decode((string) ($envelope['signature_payload'] ?? ''), true);
        if ($providedPayload === false) {
            return [
                'valid'        => false,
                'reason'       => 'Signature payload is not valid base64',
                'signed_at'    => $signedAt,
                'tsp_provider' => 'mock',
                'cert_chain'   => [],
            ];
        }

        $valid = hash_equals($expectedPayload, $providedPayload);

        return [
            'valid'        => $valid,
            'reason'       => $valid ? 'Signature verified against application key (mock TSP).' : 'Signature payload does not match expected HMAC.',
            'signed_at'    => $signedAt,
            'tsp_provider' => 'mock',
            'cert_chain'   => ['MOCK-ROOT', $envelope['tsp_certificate_id'] ?? ''],
        ];
    }
}
