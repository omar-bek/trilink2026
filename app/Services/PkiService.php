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
        if (!$resource) {
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

        if (!$success) {
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
        if ($privateKey) {
            $signature = $this->sign($contractHash, $privateKey);
        } else {
            $signature = hash_hmac('sha256', $contractHash, config('app.key'));
        }

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
