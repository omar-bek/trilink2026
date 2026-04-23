<?php

namespace App\Services\Credit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Al Etihad Credit Bureau (AECB) commercial credit scoring provider.
 *
 * AECB is the UAE's official credit bureau (Federal Law No. 6 of 2010).
 * All UAE banks and AECB-registered subscribers can pull commercial
 * credit reports keyed by trade licence number. Real bureau calls are
 * metered and billed per report, so every successful pull is cached for
 * {@see CreditScoringService::CACHE_TTL_DAYS} days and each response is
 * persisted to the credit_scores audit trail.
 *
 * Authentication is OAuth 2.0 client-credentials: POST {base}/oauth/token
 * with client_id + client_secret, cache the access_token until 60s before
 * expiry, then send it as a Bearer token on every report request.
 *
 * Request shape (AECB Commercial Credit Report v2):
 *   POST {base}/v2/commercial/report
 *   { "tradeLicenseNumber": "CN-1234567", "emirate": "DUBAI",
 *     "subjectType": "ESTABLISHMENT" }
 *
 * Response shape (trimmed to what we persist):
 *   { "bureauScore": 722,
 *     "scoreBand": "GOOD",
 *     "reportDate": "2026-04-22T10:14:00Z",
 *     "scoreFactors": [{"code":"UTIL","description":"High utilisation"}, ...] }
 *
 * Failure modes we handle explicitly:
 *   - Credentials unset     → return error, caller falls back to Mock
 *   - Token endpoint 4xx/5xx → return error, do NOT persist a score
 *   - Report 404 (no file)   → success=true, score=null, band=null
 *                               (company just isn't in the bureau yet)
 *   - Report 429 rate-limit  → return error, let retry logic handle it
 *
 * Swap-in is a single env change — no code path in the application
 * depends on this class directly, only on CreditScoringProviderInterface.
 */
class AecbCreditScoringProvider implements CreditScoringProviderInterface
{
    private const TOKEN_CACHE_KEY = 'credit:aecb:access_token';
    private const TOKEN_EARLY_REFRESH_SECONDS = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $subscriberCode,
        private readonly int $timeout = 15,
    ) {}

    public function code(): string
    {
        return 'aecb';
    }

    public function fetchScore(string $registrationNumber, ?string $country = null): array
    {
        $registrationNumber = trim($registrationNumber);

        if ($registrationNumber === '') {
            return $this->failure('Empty registration number');
        }

        // AECB only covers the UAE. For any non-UAE company the caller
        // should route to a different provider via the service binding.
        if ($country !== null && strtoupper($country) !== 'AE') {
            return $this->failure('AECB covers UAE only (company country: '.$country.')');
        }

        if ($this->clientId === '' || $this->clientSecret === '' || $this->subscriberCode === '') {
            return $this->failure('AECB credentials not configured');
        }

        try {
            $token = $this->accessToken();
        } catch (Throwable $e) {
            Log::warning('AECB token fetch failed', ['error' => $e->getMessage()]);
            return $this->failure('Unable to authenticate with AECB');
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Subscriber-Code' => $this->subscriberCode,
                ])
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/').'/v2/commercial/report', [
                    'tradeLicenseNumber' => $registrationNumber,
                    'subjectType' => 'ESTABLISHMENT',
                    'purpose' => 'BUSINESS_DUE_DILIGENCE',
                ]);
        } catch (Throwable $e) {
            Log::warning('AECB report call failed', [
                'registration' => $registrationNumber,
                'error' => $e->getMessage(),
            ]);
            return $this->failure('AECB request failed: '.$e->getMessage());
        }

        // 404 means the bureau doesn't have a file for this trade licence
        // yet — that's a normal outcome for newly-registered companies.
        // Return success with null score so we don't retry forever.
        if ($response->status() === 404) {
            return [
                'success' => true,
                'score' => null,
                'band' => null,
                'reasons' => ['No credit file on record with AECB'],
                'reported_at' => now()->toIso8601String(),
                'error' => null,
            ];
        }

        if (! $response->successful()) {
            return $this->failure('AECB returned HTTP '.$response->status());
        }

        $body = $response->json();
        $score = isset($body['bureauScore']) ? (int) $body['bureauScore'] : null;

        if ($score === null) {
            return $this->failure('AECB response missing bureauScore');
        }

        return [
            'success' => true,
            'score' => $score,
            'band' => $this->normaliseBand($body['scoreBand'] ?? null, $score),
            'reasons' => $this->extractReasons($body['scoreFactors'] ?? []),
            'reported_at' => $body['reportDate'] ?? now()->toIso8601String(),
            'error' => null,
        ];
    }

    /**
     * Cached OAuth 2.0 access token. AECB returns tokens that live for
     * roughly 1 hour; we cache until 60s before `expires_in` so a token
     * that's about to expire is never handed to a request.
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post(rtrim($this->baseUrl, '/').'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'commercial.read',
            ])
            ->throw();

        $body = $response->json();
        $token = $body['access_token'] ?? null;
        $expiresIn = (int) ($body['expires_in'] ?? 3600);

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('AECB token endpoint returned no access_token');
        }

        $ttl = max(60, $expiresIn - self::TOKEN_EARLY_REFRESH_SECONDS);
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * Normalise AECB's band codes onto the application's 4-band scale.
     * AECB actually returns bands like "VERY_LOW_RISK" / "LOW_RISK" /
     * "MEDIUM_RISK" / "HIGH_RISK"; older responses use single letters.
     * Fall back to the numeric score when the band code is unexpected.
     */
    private function normaliseBand(?string $bureauBand, int $score): string
    {
        $band = strtoupper((string) $bureauBand);
        return match (true) {
            in_array($band, ['VERY_LOW_RISK', 'A', 'EXCELLENT'], true) => 'excellent',
            in_array($band, ['LOW_RISK', 'B', 'GOOD'], true)           => 'good',
            in_array($band, ['MEDIUM_RISK', 'C', 'FAIR'], true)        => 'fair',
            in_array($band, ['HIGH_RISK', 'D', 'POOR'], true)          => 'poor',
            $score >= 750 => 'excellent',
            $score >= 650 => 'good',
            $score >= 500 => 'fair',
            default       => 'poor',
        };
    }

    /**
     * AECB scoreFactors is an array of { code, description } objects.
     * We persist the human-readable description so the supplier profile
     * can render them as-is without another lookup.
     *
     * @param  array<int, mixed>  $factors
     * @return array<int, string>
     */
    private function extractReasons(array $factors): array
    {
        $out = [];
        foreach ($factors as $factor) {
            if (is_array($factor) && isset($factor['description']) && is_string($factor['description'])) {
                $out[] = $factor['description'];
            } elseif (is_string($factor)) {
                $out[] = $factor;
            }
            if (count($out) >= 5) {
                break;
            }
        }
        return $out;
    }

    /**
     * @return array{
     *     success: bool, score: int|null, band: string|null,
     *     reasons: array<int, string>, reported_at: string|null,
     *     error: string|null,
     * }
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'score' => null,
            'band' => null,
            'reasons' => [],
            'reported_at' => null,
            'error' => $message,
        ];
    }
}
