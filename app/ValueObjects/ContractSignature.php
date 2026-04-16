<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Read-only view of one entry inside Contract.signatures JSON.
 *
 * The audit fields (ip, user_agent, contract_hash, consent_at) carry the
 * Federal Decree-Law 46/2021 evidentiary metadata that public verifiers
 * inspect. Centralising the shape here means a renamed key would surface
 * as a static analysis error instead of a silent null in the verifier
 * page.
 */
final class ContractSignature
{
    public function __construct(
        public readonly ?int $companyId,
        public readonly ?int $signedBy,
        public readonly ?CarbonInterface $signedAt,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $contractHash = null,
        public readonly ?CarbonInterface $consentAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            signedBy: isset($data['signed_by']) ? (int) $data['signed_by'] : null,
            signedAt: self::parseDate($data['signed_at'] ?? null),
            ipAddress: isset($data['ip_address']) ? (string) $data['ip_address'] : null,
            userAgent: isset($data['user_agent']) ? (string) $data['user_agent'] : null,
            contractHash: isset($data['contract_hash']) ? (string) $data['contract_hash'] : null,
            consentAt: self::parseDate($data['consent_at'] ?? null),
        );
    }

    /** @return list<self> */
    public static function collection(?array $rows): array
    {
        if (! $rows) {
            return [];
        }

        return array_values(array_map(self::fromArray(...), $rows));
    }

    private static function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'signed_by' => $this->signedBy,
            'signed_at' => $this->signedAt?->toIso8601String(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'contract_hash' => $this->contractHash,
            'consent_at' => $this->consentAt?->toIso8601String(),
        ], fn ($v) => $v !== null);
    }
}
