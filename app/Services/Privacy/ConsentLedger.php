<?php

namespace App\Services\Privacy;

use App\Models\Consent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The only legitimate writer for the `consents` table.
 *
 * Why a service instead of letting controllers `Consent::create(...)`
 * directly: PDPL Article 6 requires the consent to be evidenced — meaning
 * we need to capture the request context (IP, user-agent) consistently
 * EVERY time, not just whenever the developer remembered to. Funnelling
 * every write through this class makes that capture mandatory and turns
 * "we logged 80% of the consent flips" into "we logged 100%".
 *
 * The ledger is append-only. Granting a previously-withdrawn consent
 * does NOT update the old row — it inserts a fresh row. This way the
 * full history is preserved and we can answer questions like "did this
 * user have marketing consent on Jan 12 at 14:00?" by querying the
 * table at that timestamp.
 */
class ConsentLedger
{
    public function __construct(
        private readonly ?Request $request = null,
    ) {
    }

    /**
     * Record that the user granted (or re-granted) a consent. The latest
     * non-withdrawn row for the same (user, type) is left untouched —
     * grants stack — but a fresh row is inserted with the current
     * version + capture context. The current grant is whichever row
     * has the latest granted_at and a null withdrawn_at.
     */
    public function grant(User $user, string $type, string $version): Consent
    {
        if (!in_array($type, Consent::ALL_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown consent type: {$type}");
        }

        // Phase 2.5 — link the consent to the immutable policy version
        // snapshot if one exists for this version string. The lookup
        // is by version because that's the public-facing identifier
        // the controller has at hand. Returns null silently for
        // grant types that aren't tied to a published document
        // (cookies_essential, marketing_email — those are toggle-style
        // and don't have a policy text behind them).
        $policyVersionId = null;
        if ($type === \App\Models\Consent::TYPE_PRIVACY_POLICY
            || $type === \App\Models\Consent::TYPE_DATA_PROCESSING) {
            $policyVersionId = \App\Models\PrivacyPolicyVersion::query()
                ->where('version', $version)
                ->value('id');
        }

        return Consent::create([
            'user_id'                   => $user->id,
            'consent_type'              => $type,
            'version'                   => $version,
            'privacy_policy_version_id' => $policyVersionId,
            'granted_at'                => CarbonImmutable::now(),
            'withdrawn_at'              => null,
            'ip_address'                => $this->captureIp(),
            'user_agent'                => $this->captureUserAgent(),
        ]);
    }

    /**
     * Mark every active grant of this consent type for this user as
     * withdrawn AS OF NOW. Inserts a withdrawal row first (so the new
     * row carries the IP+UA of the withdrawal action) then stamps the
     * existing active rows so future queries see the withdrawal.
     *
     * Returns the number of rows that were affected.
     */
    public function withdraw(User $user, string $type): int
    {
        if (!in_array($type, Consent::ALL_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown consent type: {$type}");
        }

        $now = CarbonImmutable::now();

        $affected = Consent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', $type)
            ->whereNotNull('granted_at')
            ->whereNull('withdrawn_at')
            ->update([
                'withdrawn_at' => $now,
                'updated_at'   => $now,
            ]);

        if ($affected > 0) {
            // Drop a marker row showing WHO/WHEN/WHERE the withdrawal
            // came from. Without this, an audit can see "withdrawn at
            // 14:32 from IP X" only on the original grant row, which
            // looks like the original grant was tampered with.
            Consent::create([
                'user_id'      => $user->id,
                'consent_type' => $type,
                'version'      => 'withdrawal',
                'granted_at'   => null,
                'withdrawn_at' => $now,
                'ip_address'   => $this->captureIp(),
                'user_agent'   => $this->captureUserAgent(),
            ]);
        }

        return $affected;
    }

    /**
     * Check whether the user currently has an active grant for this
     * consent type. Used by the cookie banner ("should I show?") and
     * by marketing dispatch ("can I email this user?").
     */
    public function hasActive(User $user, string $type): bool
    {
        return Consent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', $type)
            ->whereNotNull('granted_at')
            ->whereNull('withdrawn_at')
            ->exists();
    }

    /**
     * Full ledger for the user — used by the privacy dashboard "Consent
     * history" panel and by the DSAR export to bundle the consent log
     * into the user's data archive.
     *
     * @return \Illuminate\Support\Collection<int, Consent>
     */
    public function ledgerFor(User $user): \Illuminate\Support\Collection
    {
        return Consent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
    }

    private function captureIp(): ?string
    {
        return $this->request?->ip() ?? request()?->ip();
    }

    private function captureUserAgent(): ?string
    {
        return $this->request?->userAgent() ?? request()?->userAgent();
    }
}
