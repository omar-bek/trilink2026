<?php

namespace App\Services\Credit;

/**
 * Common contract every credit scoring provider must implement.
 *
 * Phase 2 / Sprint 10 / task 2.16. Today the only implementation is the
 * MockCreditScoringProvider used for tests + demos. Phase 3 brings:
 *
 *   - AECB     (Al Etihad Credit Bureau, UAE) — official UAE bureau
 *   - SIMAH    (Saudi Credit Bureau)          — official KSA bureau
 *   - D&B      (Dun & Bradstreet)             — international fallback
 *
 * Like the sanctions provider abstraction (Sprint 7 / 2.1), the
 * application code only depends on this interface so swapping providers
 * is an env change rather than a refactor.
 *
 * Returned shape is intentionally tight: a numeric score on a fixed 0-1000
 * scale (matches AECB), a band ("excellent" / "good" / "fair" / "poor"),
 * and an optional set of reasons. The CreditScoringService layer
 * normalises everything else (currency, time-on-file, regional naming).
 */
interface CreditScoringProviderInterface
{
    /**
     * Stable provider code persisted on every score row.
     */
    public function code(): string;

    /**
     * Pull a credit report for the given company.
     *
     * @param  string  $registrationNumber  Trade license / commercial registration
     * @param  string|null  $country  ISO-2 country code (selects bureau)
     * @return array{
     *     success: bool,
     *     score: int|null,
     *     band: string|null,
     *     reasons: array<int, string>,
     *     reported_at: string|null,
     *     error: string|null,
     * }
     */
    public function fetchScore(string $registrationNumber, ?string $country = null): array;
}
