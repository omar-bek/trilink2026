<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Rfq;
use Illuminate\Support\Collection;

/**
 * Phase 7 (UAE Compliance Roadmap) — bid-rigging and collusion
 * detection. Federal Decree-Law 36/2023 (Competition Law) prohibits
 * agreements between competitors that restrict competition, including
 * bid rigging, price fixing and market allocation. The platform has a
 * duty to detect and report suspicious patterns — or risk being seen
 * as a facilitator.
 *
 * The service runs FIVE detection patterns against every closed RFQ.
 * Each pattern returns zero or more findings with a severity:
 *
 *   - critical — shared beneficial owner across competing bids. This
 *     is the textbook bid-rigging scenario and virtually never a
 *     false positive.
 *   - high     — shared IP address, submission-timing clustering.
 *     May be a false positive (co-working space, corporate network)
 *     but worth investigating.
 *   - medium   — shared email domain (excluding generic providers),
 *     phone prefix overlap. Correlative, not dispositive.
 *
 * The service is PURE — it reads data and returns findings. It does
 * NOT mutate bids, block awards or send notifications. The caller
 * (AnalyzeRfqForCollusionJob) decides what to do with the findings.
 *
 * PDPL compliance: beneficial owner id_numbers are NEVER stored in
 * the findings — only their sha1 hash. An inspector can cross-
 * reference the hash with the BO table (with appropriate access) but
 * the raw PII never leaves the encrypted column.
 */
class AntiCollusionService
{
    /**
     * Generic email domains that are NOT suspicious when shared
     * across bidders — exclude from Pattern 3. Configurable via
     * config('anticollusion.generic_domains') when tenants want
     * to extend the list.
     */
    private const GENERIC_DOMAINS = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'live.com', 'icloud.com', 'aol.com', 'protonmail.com',
        'mail.com', 'yandex.com',
    ];

    /**
     * Analyze an RFQ's bids for collusion patterns.
     *
     * @return Collection<int, array{type: string, severity: string, evidence: array}>
     */
    public function analyzeRfq(Rfq $rfq): Collection
    {
        $rfq->loadMissing(['bids.company.beneficialOwners', 'bids.provider']);
        $bids = $rfq->bids;

        if ($bids->count() < 2) {
            return collect();
        }

        $patterns = collect();

        $patterns = $patterns->merge($this->detectSelfBidding($rfq, $bids));
        $patterns = $patterns->merge($this->detectSharedIps($bids));
        $patterns = $patterns->merge($this->detectSharedBeneficialOwners($bids));
        $patterns = $patterns->merge($this->detectSharedEmailDomains($bids));
        $patterns = $patterns->merge($this->detectTimingClustering($bids));
        $patterns = $patterns->merge($this->detectSharedPhonePrefix($bids));

        return $patterns->values();
    }

    /**
     * Pattern 0 — Self-bidding. The company that published the RFQ has
     * also submitted a bid on it. This is a direct conflict of interest
     * that the other 5 cross-company patterns cannot catch (they all
     * require distinctCompanies >= 2, which excludes the owner). Severity
     * is CRITICAL because it's never a false positive — a company
     * evaluating its own bid is textbook self-dealing.
     */
    private function detectSelfBidding(Rfq $rfq, Collection $bids): Collection
    {
        $ownerCompanyId = $rfq->company_id;
        if (! $ownerCompanyId) {
            return collect();
        }

        $selfBids = $bids->filter(fn ($b) => (int) $b->company_id === (int) $ownerCompanyId);
        if ($selfBids->isEmpty()) {
            return collect();
        }

        return collect([[
            'type' => 'self_bidding',
            'severity' => 'critical',
            'evidence' => [
                'rfq_owner_company_id' => $ownerCompanyId,
                'bid_ids' => $selfBids->pluck('id')->all(),
                'company_ids' => [$ownerCompanyId],
            ],
        ]]);
    }

    /**
     * Pattern 1 — Shared login IP address across different companies
     * bidding on the same RFQ. Two users from different companies
     * submitting from the same IP = likely same physical location
     * or VPN, which is a bid-rigging signal.
     *
     * False positive risk: HIGH (co-working spaces, corporate VPNs).
     * That's why severity is 'high' not 'critical' — the admin must
     * investigate before acting.
     */
    private function detectSharedIps(Collection $bids): Collection
    {
        $findings = collect();

        // The IP at bid-submission time lives in the audit_logs table
        // as the most recent `create` action on the Bid resource. We
        // fall back to the provider's last_login_ip if available,
        // then to the audit log. When neither exists the bid drops
        // out of the IP analysis (no false positives from missing data).
        $bidIps = $bids->mapWithKeys(function ($bid) {
            $ip = $bid->provider?->last_login_ip
                ?? AuditLog::query()
                    ->where('resource_type', 'Bid')
                    ->where('resource_id', $bid->id)
                    ->whereIn('action', ['create', 'submit'])
                    ->orderByDesc('id')
                    ->value('ip_address');

            return [$bid->id => $ip];
        });

        $ipGroups = $bids->groupBy(fn ($b) => $bidIps[$b->id] ?? '__none__');
        foreach ($ipGroups as $ip => $group) {
            if ($ip === '__none__' || $group->count() < 2) {
                continue;
            }
            $distinctCompanies = $group->pluck('company_id')->unique();
            if ($distinctCompanies->count() < 2) {
                continue;
            }
            $findings->push([
                'type' => 'shared_ip',
                'severity' => 'high',
                'evidence' => [
                    'ip' => $ip,
                    'bid_ids' => $group->pluck('id')->all(),
                    'company_ids' => $distinctCompanies->all(),
                ],
            ]);
        }

        return $findings;
    }

    /**
     * Pattern 2 — Shared beneficial owner across competing bidders.
     * Two companies with the same BO submitting competing bids is the
     * textbook bid-rigging scenario. Severity: CRITICAL.
     *
     * PDPL: the BO id_number is encrypted in the DB. We hash it
     * again with sha1 for the evidence payload so the raw number
     * never appears outside the encrypted column.
     */
    private function detectSharedBeneficialOwners(Collection $bids): Collection
    {
        $findings = collect();

        // Build a flat list of (bid_id, company_id, id_number_hash) tuples.
        $tuples = $bids->flatMap(function ($bid) {
            $bos = $bid->company?->beneficialOwners ?? collect();

            return $bos->map(fn ($bo) => [
                'bid_id' => $bid->id,
                'company_id' => $bid->company_id,
                'id_number_hash' => $bo->id_number ? sha1((string) $bo->id_number) : null,
            ]);
        })->filter(fn ($t) => $t['id_number_hash'] !== null);

        $boGroups = $tuples->groupBy('id_number_hash');
        foreach ($boGroups as $hash => $group) {
            $distinctCompanies = $group->pluck('company_id')->unique();
            if ($distinctCompanies->count() < 2) {
                continue;
            }
            $findings->push([
                'type' => 'shared_beneficial_owner',
                'severity' => 'critical',
                'evidence' => [
                    'id_number_hash' => $hash,
                    'bid_ids' => $group->pluck('bid_id')->unique()->all(),
                    'company_ids' => $distinctCompanies->all(),
                ],
            ]);
        }

        return $findings;
    }

    /**
     * Pattern 3 — Shared email domain (excluding generic providers).
     * Two companies with @companyname.ae submitting from the same
     * domain suggests they're related entities.
     */
    private function detectSharedEmailDomains(Collection $bids): Collection
    {
        $findings = collect();
        $genericDomains = array_merge(
            self::GENERIC_DOMAINS,
            (array) config('anticollusion.generic_domains', [])
        );

        $domainMap = $bids->mapWithKeys(function ($bid) {
            $email = $bid->company?->email ?? '';
            $domain = mb_strtolower(substr(strrchr($email, '@') ?: '', 1));

            return [$bid->id => ['company_id' => $bid->company_id, 'domain' => $domain]];
        })->filter(fn ($d) => $d['domain'] !== '');

        $domainGroups = $domainMap->groupBy('domain');
        foreach ($domainGroups as $domain => $group) {
            if (in_array($domain, $genericDomains, true)) {
                continue;
            }
            $distinctCompanies = $group->pluck('company_id')->unique();
            if ($distinctCompanies->count() < 2) {
                continue;
            }
            $findings->push([
                'type' => 'shared_email_domain',
                'severity' => 'medium',
                'evidence' => [
                    'domain' => $domain,
                    'bid_ids' => array_keys($group->all()),
                    'company_ids' => $distinctCompanies->all(),
                ],
            ]);
        }

        return $findings;
    }

    /**
     * Pattern 4 — Submission timing clustering. Two or more bids
     * submitted within a 10-minute window from DIFFERENT companies
     * suggests coordinated timing — the bidders may have agreed on
     * who submits what and when.
     */
    private function detectTimingClustering(Collection $bids): Collection
    {
        $findings = collect();
        $windowMinutes = 10;

        $sorted = $bids->sortBy('created_at')->values();
        for ($i = 0; $i < $sorted->count(); $i++) {
            $cluster = collect([$sorted[$i]]);
            for ($j = $i + 1; $j < $sorted->count(); $j++) {
                $diffMinutes = $sorted[$i]->created_at?->diffInMinutes($sorted[$j]->created_at, true) ?? 999;
                if ($diffMinutes <= $windowMinutes) {
                    $cluster->push($sorted[$j]);
                } else {
                    break;
                }
            }
            $distinctCompanies = $cluster->pluck('company_id')->unique();
            if ($distinctCompanies->count() >= 2) {
                $findings->push([
                    'type' => 'timing_clustering',
                    'severity' => 'high',
                    'evidence' => [
                        'window_minutes' => $windowMinutes,
                        'bid_ids' => $cluster->pluck('id')->all(),
                        'company_ids' => $distinctCompanies->all(),
                        'earliest' => $cluster->min('created_at')?->toIso8601String(),
                        'latest' => $cluster->max('created_at')?->toIso8601String(),
                    ],
                ]);
                // Skip past this cluster so we don't emit overlapping findings.
                $i += $cluster->count() - 1;
            }
        }

        return $findings;
    }

    /**
     * Pattern 5 — Shared phone number prefix. Two companies with
     * phone numbers sharing the first 8 digits (after stripping
     * whitespace + the country code) suggests the same switchboard
     * — a weaker but still correlative indicator.
     */
    private function detectSharedPhonePrefix(Collection $bids): Collection
    {
        $findings = collect();

        $phoneMap = $bids->mapWithKeys(function ($bid) {
            $phone = preg_replace('/[\s\-\(\)]+/', '', (string) ($bid->company?->phone ?? ''));
            // Strip leading +971 / 00971 / 0 so the prefix comparison
            // works regardless of how the phone was entered.
            $phone = preg_replace('/^(\+?00?971|0)/', '', $phone);
            $prefix = mb_substr($phone, 0, 8);

            return [$bid->id => ['company_id' => $bid->company_id, 'prefix' => $prefix]];
        })->filter(fn ($d) => mb_strlen($d['prefix']) >= 6);

        $prefixGroups = $phoneMap->groupBy('prefix');
        foreach ($prefixGroups as $prefix => $group) {
            $distinctCompanies = $group->pluck('company_id')->unique();
            if ($distinctCompanies->count() < 2) {
                continue;
            }
            $findings->push([
                'type' => 'shared_phone_prefix',
                'severity' => 'medium',
                'evidence' => [
                    'prefix' => $prefix,
                    'bid_ids' => array_keys($group->all()),
                    'company_ids' => $distinctCompanies->all(),
                ],
            ]);
        }

        return $findings;
    }
}
