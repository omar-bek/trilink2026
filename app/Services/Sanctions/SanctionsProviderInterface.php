<?php

namespace App\Services\Sanctions;

/**
 * Common contract every sanctions screening provider must implement.
 *
 * Providers (OpenSanctions, Refinitiv World-Check, Dow Jones, ComplyAdvantage)
 * differ wildly in their request format, response shape, pricing, and rate
 * limits. The application code only depends on this interface — swapping
 * providers is an env change, not a refactor.
 *
 * Why an interface and not direct calls?
 *   1. Phase 2 / Sprint 7 / task 2.1 requires it explicitly.
 *   2. Refinitiv comes online in Phase 3 once we have enterprise customers
 *      paying for it; until then OpenSanctions is the free fallback.
 *   3. Tests fake the provider with an in-memory implementation so the CI
 *      run doesn't hit the real API.
 *
 * The result envelope is intentionally narrow: providers normalise their
 * response into the same four-key shape so {@see SanctionsScreeningService}
 * doesn't have to know which provider produced it.
 */
interface SanctionsProviderInterface
{
    /**
     * Stable provider code persisted on every screening row. Used for
     * backfills and reporting ("how many hits did Refinitiv flag last
     * quarter?"). Lowercase, no spaces.
     */
    public function code(): string;

    /**
     * Run a screening against the provider's watchlist.
     *
     * @param  string  $name  Company legal name as registered on TriLink.
     * @param  string|null  $country  ISO-2 country code (helps disambiguate
     *                                common names like "Global Trading").
     *
     * @return array{
     *     result: 'clean'|'hit'|'review'|'error',
     *     match_count: int,
     *     matched_entities: array<int, array<string, mixed>>|null,
     *     notes: string|null,
     * }
     */
    public function screen(string $name, ?string $country = null): array;
}
