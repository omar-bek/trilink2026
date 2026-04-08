<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditScore;
use App\Services\Credit\CreditScoringProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Façade over the credit scoring provider abstraction.
 *
 * Phase 2 / Sprint 10 / task 2.16. Caches results for 30 days (credit
 * scores don't move that fast and bureau API calls cost real money),
 * persists each fetch to the credit_scores table for audit + history,
 * and exposes a small {@see scoreFor} convenience that any controller
 * can call without thinking about the provider.
 *
 * The companies table also gets a denormalised `latest_credit_score`
 * + `latest_credit_band` written here so the supplier profile / bid
 * card can render the score without joining credit_scores on every
 * page render.
 */
class CreditScoringService
{
    private const CACHE_TTL_DAYS = 30;

    public function __construct(
        private readonly CreditScoringProviderInterface $provider,
    ) {
    }

    /**
     * Fetch a credit score for the company. Returns the persisted
     * CreditScore row (or null if the bureau couldn't return anything).
     */
    public function scoreFor(Company $company, bool $useCache = true): ?CreditScore
    {
        $registration = (string) $company->registration_number;
        if ($registration === '') {
            return null;
        }

        $cacheKey = 'credit:' . $this->provider->code() . ':' . md5($registration . '|' . ($company->country ?? ''));

        $result = $useCache
            ? Cache::remember(
                $cacheKey,
                now()->addDays(self::CACHE_TTL_DAYS),
                fn () => $this->provider->fetchScore($registration, $company->country),
            )
            : $this->provider->fetchScore($registration, $company->country);

        if (!($result['success'] ?? false) || $result['score'] === null) {
            return null;
        }

        $row = CreditScore::create([
            'company_id'   => $company->id,
            'provider'     => $this->provider->code(),
            'score'        => $result['score'],
            'band'         => $result['band'],
            'reasons'      => $result['reasons'] ?? [],
            'reported_at'  => $result['reported_at'] ?? now(),
        ]);

        // Denormalise onto the company row so the bid card / supplier
        // profile don't have to join credit_scores on every render.
        $company->update([
            'latest_credit_score' => $result['score'],
            'latest_credit_band'  => $result['band'],
        ]);

        return $row;
    }
}
