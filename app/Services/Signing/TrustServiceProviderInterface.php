<?php

namespace App\Services\Signing;

/**
 * Phase 6 (UAE Compliance Roadmap) — abstraction over a TDRA-accredited
 * Trust Service Provider (TSP). Concrete implementations:
 *
 *   - {@see MockTspProvider}     — local CAdES emulator for tests + the
 *                                  period before a commercial TSP contract
 *                                  is signed.
 *   - {@see ComtrustTspProvider} — Comtrust e-Sign API skeleton.
 *   - {@see EsspTspProvider}     — ESSP TrustSign API skeleton.
 *
 * The dispatcher binds the right provider via config('signing.default_tsp_provider')
 * — adding a new TSP is one entry in config/signing.php + a new class.
 *
 * Contract:
 *
 *   - Implementations are STATELESS. Any state lives on the contract's
 *     `signatures` JSON column, not on the service.
 *
 *   - `signHash()` accepts the contract content hash + signer metadata
 *     and returns an envelope dictionary the caller can persist:
 *       {
 *         'tsp_provider'        => 'comtrust',
 *         'tsp_certificate_id'  => '...',
 *         'signature_format'    => 'CAdES' | 'PAdES' | 'XAdES',
 *         'signature_payload'   => base64-encoded bytes,
 *         'timestamp_token'     => base64-encoded RFC 3161 token,
 *         'signed_at'           => ISO-8601 string,
 *       }
 *
 *   - `verify()` accepts the same envelope plus the original hash
 *     and returns a structured verification result. Used by the
 *     public /contracts/{id}/verify endpoint.
 *
 *   - Implementations MUST NOT throw on verification failure — they
 *     return a result with `valid = false` and a reason. They MAY
 *     throw on transmission failures during sign() (network down,
 *     credentials wrong) — the caller logs and surfaces an error
 *     to the user.
 */
interface TrustServiceProviderInterface
{
    public function name(): string;

    /**
     * Produce an Advanced or Qualified signature envelope for the
     * given content hash. Returns the envelope as an associative
     * array (see contract docblock).
     *
     * @return array<string, mixed>
     */
    public function signHash(string $contractHash, array $signerContext): array;

    /**
     * Verify a previously-signed envelope against the original hash.
     * Returns a result of the shape:
     *   {
     *     'valid'        => bool,
     *     'reason'       => string,
     *     'signed_at'    => ?string,
     *     'tsp_provider' => string,
     *     'cert_chain'   => array<int, string>,
     *   }
     *
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public function verify(string $contractHash, array $envelope): array;
}
