<?php

namespace App\ValueObjects;

/**
 * Read-only view of one entry inside Contract.parties JSON.
 *
 * Why: the parties column stores arrays of magic string keys
 * (`company_id`, `role`, `name`). Accessing them with `$p['role'] ?? ''`
 * everywhere creates two problems: (1) typos go undetected by static
 * analysis, (2) the shape is invisible to anyone reading the calling code.
 * Hydrating into this VO makes the schema explicit and gives IDE autocomplete.
 *
 * Storage is unchanged — Contract.parties is still a plain JSON array, and
 * fromArray()/toArray() bridges the two representations.
 */
final class ContractParty
{
    public function __construct(
        public readonly ?int $companyId,
        public readonly string $role,
        public readonly ?string $name = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            role: strtolower((string) ($data['role'] ?? '')),
            name: isset($data['name']) ? (string) $data['name'] : null,
        );
    }

    /**
     * Hydrate every party row of a contract. Accepts the raw JSON-decoded
     * value (array | null) and always returns an array, never null.
     *
     * @return list<self>
     */
    public static function collection(?array $rows): array
    {
        if (! $rows) {
            return [];
        }

        return array_values(array_map(self::fromArray(...), $rows));
    }

    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    public function toArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'role' => $this->role,
            'name' => $this->name,
        ], fn ($v) => $v !== null);
    }
}
