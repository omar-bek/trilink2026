<?php

namespace App\Services;

use RuntimeException;

class PkiService
{
    public function generateKeyPair(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        if (! $resource) {
            throw new RuntimeException('Failed to generate key pair');
        }

        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function sign(string $data, string $privateKey): string
    {
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new RuntimeException('Failed to sign data');
        }

        return base64_encode($signature);
    }

    public function verify(string $data, string $signature, string $publicKey): bool
    {
        $decodedSignature = base64_decode($signature);
        $result = openssl_verify($data, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    public function generateContractHash(array $contractData): string
    {
        $dataString = json_encode($contractData, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

        return hash('sha256', $dataString);
    }

    public function createDigitalSignature(int $userId, int $companyId, string $contractHash, ?string $privateKey = null): array
    {
        // Phase 0 hardening — the previous implementation silently fell back
        // to hash_hmac(APP_KEY) when no private key was provided. That is
        // *not* a digital signature: any party who knows APP_KEY (i.e. every
        // server engineer) can forge it, and no third party (e.g. a UAE
        // court) can verify it independently. Federal Decree-Law 46/2021
        // requires the signing key to be under the sole control of the
        // signatory. Anything weaker isn't a signature, it's a MAC.
        //
        // We now hard-fail rather than emit a fake "signature". Callers
        // that hit this path must either supply a real RSA private key or
        // wire up a Trust Service Provider in Phase 6 (UAE Pass / Comtrust).
        if (empty($privateKey)) {
            throw new RuntimeException(
                'Cannot create a digital signature without a private key. '
                .'The HMAC fallback was removed in Phase 0 (UAE Compliance Roadmap). '
                .'Provide an RSA private key or integrate a Trust Service Provider.'
            );
        }

        $signature = $this->sign($contractHash, $privateKey);

        return [
            'user_id' => $userId,
            'company_id' => $companyId,
            'signature' => $signature,
            'algorithm' => 'SHA256withRSA',
            'certificate' => null,
            'signed_at' => now()->toISOString(),
            'contract_hash' => $contractHash,
        ];
    }
}
