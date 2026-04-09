<?php

namespace App\Services\Signing;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phase 6 (UAE Compliance Roadmap) — UAE Pass OAuth flow.
 *
 * UAE Pass is the national digital identity issued by TDRA. A
 * signature backed by a UAE Pass identity assertion satisfies the
 * Advanced grade under Federal Decree-Law 46/2021 Article 18 because
 * the signing user is uniquely identified by their Emirates ID via
 * a government-verified channel — the same identity assertion the
 * UAE government uses for visas, banking and tax filings.
 *
 * Flow (OAuth 2.0 Authorization Code):
 *
 *   1. User clicks "Sign with UAE Pass" on the contract show page.
 *      The controller calls buildAuthorizationUrl() and redirects.
 *      A `state` value is stamped in the session for CSRF defence.
 *
 *   2. UAE Pass shows the consent screen + push prompt to the
 *      user's UAE Pass app. After approval, UAE Pass redirects
 *      back to our `redirect_uri` with a `code` query parameter.
 *
 *   3. The callback controller calls handleCallback() which:
 *        - validates the state matches the session value
 *        - exchanges the code for an access_token via the token endpoint
 *        - calls the userinfo endpoint to fetch the verified profile
 *        - returns a normalised IdentityAssertion array
 *
 *   4. ContractService::sign uses the assertion to stamp the
 *      signature row with `uae_pass_user_id`, `signer_full_name`,
 *      and the achieved grade (Advanced).
 *
 * The provider is gated by config('uae_pass.enabled'). When OFF,
 * buildAuthorizationUrl + handleCallback throw a clear error so the
 * controller can short-circuit to the platform's existing Simple
 * signature flow.
 */
class UaePassProvider
{
    public function isEnabled(): bool
    {
        return (bool) config('uae_pass.enabled', false);
    }

    /**
     * Build the URL the user is redirected to. The state value is
     * generated here and the caller stashes it in the session before
     * the redirect; the callback validates session.state ===
     * request.state to defeat CSRF.
     */
    public function buildAuthorizationUrl(string $contractId, string $state): string
    {
        $this->assertEnabled();

        $env = (string) config('uae_pass.environment', 'sandbox');
        $authorizeUrl = config("uae_pass.endpoints.{$env}.authorize");

        if (empty($authorizeUrl)) {
            throw new RuntimeException("UAE Pass authorize endpoint not configured for environment '{$env}'.");
        }

        $params = [
            'response_type' => 'code',
            'client_id'     => (string) config('uae_pass.client_id'),
            'redirect_uri'  => (string) config('uae_pass.redirect_uri'),
            'scope'         => (string) config('uae_pass.scope', 'urn:uae:digitalid:profile:general'),
            'state'         => $state,
            'acr_values'    => (string) config('uae_pass.acr_values'),
        ];

        return $authorizeUrl . '?' . http_build_query($params);
    }

    /**
     * Generate a fresh state value to bind the redirect to the
     * session. Caller stores it in session('uae_pass.state.<contract>')
     * with a TTL.
     */
    public function newState(): string
    {
        return Str::random(40);
    }

    /**
     * Handle the OAuth callback. Validates the state, exchanges the
     * code, fetches userinfo, returns a normalised assertion.
     *
     * @return array{
     *   uae_pass_user_id: string,
     *   full_name: string,
     *   emirates_id_hash: ?string,
     *   nationality: ?string,
     *   verified: bool,
     *   acr: ?string,
     *   raw: array<string, mixed>
     * }
     */
    public function handleCallback(Request $request, string $expectedState): array
    {
        $this->assertEnabled();

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            throw new RuntimeException('UAE Pass callback missing code or state.');
        }
        if (!hash_equals($expectedState, $state)) {
            throw new RuntimeException('UAE Pass callback state mismatch — possible CSRF attempt.');
        }

        $tokenResponse = $this->exchangeCode($code);
        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('UAE Pass token endpoint returned no access_token.');
        }

        $profile = $this->fetchProfile($accessToken);

        // The userinfo response shape (per UAE Pass docs):
        //   sub                      — opaque user identifier (use as user_id)
        //   fullnameAR / fullnameEN  — display name
        //   idn                      — Emirates ID number (sensitive — we hash)
        //   nationalityEN
        //   userType                 — Citizen / Resident / Visitor
        //   acr                      — authentication class reference reached
        $sub = (string) ($profile['sub'] ?? '');
        if ($sub === '') {
            throw new RuntimeException('UAE Pass userinfo returned no sub identifier.');
        }

        $emiratesId = (string) ($profile['idn'] ?? '');
        // Never store the raw Emirates ID — hash it deterministically
        // so the platform can detect cross-tenant duplicates without
        // surfacing the underlying number to anyone with DB access.
        $emiratesIdHash = $emiratesId !== '' ? hash('sha256', $emiratesId) : null;

        return [
            'uae_pass_user_id' => $sub,
            'full_name'        => (string) ($profile['fullnameEN'] ?? $profile['fullnameAR'] ?? ''),
            'emirates_id_hash' => $emiratesIdHash,
            'nationality'      => $profile['nationalityEN'] ?? null,
            'verified'         => ($profile['userType'] ?? null) === 'SOP3', // SOP3 = fully verified
            'acr'              => $profile['acr'] ?? null,
            'raw'              => $profile,
            'asserted_at'      => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    private function exchangeCode(string $code): array
    {
        $env = (string) config('uae_pass.environment', 'sandbox');
        $tokenUrl = (string) config("uae_pass.endpoints.{$env}.token");

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('uae_pass.client_id'),
                (string) config('uae_pass.client_secret')
            )
            ->post($tokenUrl, [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => (string) config('uae_pass.redirect_uri'),
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'UAE Pass token exchange failed: HTTP ' . $response->status() . ' — ' . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function fetchProfile(string $accessToken): array
    {
        $env = (string) config('uae_pass.environment', 'sandbox');
        $userinfoUrl = (string) config("uae_pass.endpoints.{$env}.userinfo");

        $response = Http::withToken($accessToken)->get($userinfoUrl);

        if (!$response->successful()) {
            throw new RuntimeException(
                'UAE Pass userinfo failed: HTTP ' . $response->status() . ' — ' . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException(
                'UAE Pass integration is disabled. Set UAE_PASS_ENABLED=true and configure UAE_PASS_CLIENT_ID / UAE_PASS_CLIENT_SECRET before enabling the redirect flow.'
            );
        }
    }
}
