<?php

namespace App\Services;

use App\Enums\VerificationLevel;
use App\Models\Company;
use App\Models\SanctionsScreening;
use App\Models\User;
use App\Notifications\SanctionsHitDetectedNotification;
use App\Services\Sanctions\SanctionsProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Sanctions watchlist screening façade.
 *
 * Phase 2 / Sprint 7 / task 2.1 split this class into two halves:
 *
 *   1. The low-level HTTP / response-shape work moved to a provider behind
 *      {@see SanctionsProviderInterface}. Today the only implementation is
 *      OpenSanctions; Phase 3 adds Refinitiv World-Check for enterprise.
 *
 *   2. This service keeps everything that should NOT change with the provider:
 *      caching, persistence on the audit log, status updates on the company,
 *      verification-level demotion on hit, and admin notification.
 *
 * Failure modes:
 *   - HTTP / network error → result=error, no status update (caller decides)
 *   - High-confidence match → result=hit + verification_level=unverified
 *     and a SanctionsHitDetectedNotification fires to all admins
 *   - Low-confidence match → result=review + verification_level pinned to
 *     "unverified" until an admin manually adjudicates from the queue
 *   - No matches → result=clean
 *
 * Results are cached for 24 hours to keep auto-screen on every page render
 * cheap. Manual re-screen always bypasses the cache.
 */
class SanctionsScreeningService
{
    private const CACHE_TTL_MINUTES = 1440; // 24h

    public function __construct(
        private readonly SanctionsProviderInterface $provider,
    ) {}

    /**
     * Run a screening for the given company. Persists the verdict on the
     * company row, writes an audit row to sanctions_screenings, and returns
     * the resulting screening record.
     */
    public function screenCompany(Company $company, ?int $triggeredBy = null, bool $useCache = true): SanctionsScreening
    {
        $query = trim((string) $company->name);
        $cacheKey = 'sanctions:'.$this->provider->code().':'.md5($query.'|'.($company->country ?? ''));

        $result = $useCache
            ? Cache::remember(
                $cacheKey,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
                fn () => $this->provider->screen($query, $company->country),
            )
            : $this->provider->screen($query, $company->country);

        $screening = SanctionsScreening::create([
            'company_id' => $company->id,
            'provider' => $this->provider->code(),
            'query' => $query,
            'result' => $result['result'],
            'match_count' => $result['match_count'],
            'matched_entities' => $result['matched_entities'],
            'triggered_by' => $triggeredBy,
            'notes' => $result['notes'] ?? null,
        ]);

        // Only update the company status when the call succeeded with a
        // meaningful verdict. An HTTP error or rate-limit keeps the
        // company at its previous status — we never want a network blip
        // (or a 429 from the upstream's daily quota) to flip a clean
        // company into "hit", AND we never want it to silently mark a
        // never-screened company as "clean" either. The screening row is
        // still persisted so the audit trail captures the failed attempt
        // and a future re-screen job can pick it up.
        $unreliable = [SanctionsScreening::RESULT_ERROR, SanctionsScreening::RESULT_RATE_LIMITED];
        if (! in_array($result['result'], $unreliable, true)) {
            $this->applyVerdict($company, $result['result']);

            if (in_array($result['result'], [SanctionsScreening::RESULT_HIT, SanctionsScreening::RESULT_REVIEW], true)) {
                $this->notifyAdmins($company, $screening);
            }
        }

        return $screening;
    }

    /**
     * Apply the screening verdict to the company row.
     *
     * On HIT: status flips to "hit", verification_level is forcibly demoted
     * to UNVERIFIED, and the company can no longer transact (BidService and
     * PaymentService both consult `isBlocked`).
     *
     * On REVIEW: status flips to "review", verification_level is pinned at
     * UNVERIFIED until an admin manually decides. The company can still
     * receive RFQs but cannot bid or accept payments.
     *
     * On CLEAN: status flips to "clean". Verification level is left
     * untouched — clean is the default state and doesn't change tier.
     */
    private function applyVerdict(Company $company, string $verdict): void
    {
        $update = [
            'sanctions_status' => $verdict,
            'sanctions_screened_at' => now(),
        ];

        if ($verdict === SanctionsScreening::RESULT_HIT
            || $verdict === SanctionsScreening::RESULT_REVIEW) {
            $update['verification_level'] = VerificationLevel::UNVERIFIED;
        }

        $company->update($update);
    }

    /**
     * Fire a database + mail notification to every admin user when a hit
     * or review verdict is recorded. Bulk-rescreen jobs may produce many
     * of these — admins handle them via the verification queue page.
     */
    private function notifyAdmins(Company $company, SanctionsScreening $screening): void
    {
        try {
            $admins = User::query()
                ->where('role', 'admin')
                ->where('status', 'active')
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new SanctionsHitDetectedNotification($company, $screening));
            }
        } catch (\Throwable $e) {
            // Notifications are best-effort: a missing column or stale
            // schema must never block the screening pipeline itself.
            report($e);
        }
    }

    /**
     * True if the company is currently flagged as a sanctions hit OR
     * pending manual review. Used by the bid + payment pipelines to block
     * transactions involving sanctioned entities.
     */
    public function isBlocked(Company $company): bool
    {
        return in_array(
            $company->sanctions_status,
            [SanctionsScreening::RESULT_HIT, SanctionsScreening::RESULT_REVIEW],
            true,
        );
    }
}
