<?php

namespace App\Services;

use App\Enums\VerificationLevel;
use App\Models\Company;
use App\Models\CompanyDocument;

/**
 * Trust-tier promotion engine. Phase 2 / Sprint 8 / task 2.5.
 *
 * Owns three operations:
 *
 *   1. {@see eligibleLevel} — given a company, returns the highest tier
 *      it currently qualifies for based on verified documents (and the
 *      beneficial-owners requirement for Gold+). This is the source of
 *      truth that drives the verification queue and the auto-promotion
 *      hook on document review.
 *
 *   2. {@see promote} — sets a company's verification_level to a target
 *      tier explicitly (admin override). Records who promoted and when
 *      so the trust badge has provenance. Also blocks promotion when
 *      the company has an unresolved sanctions hit/review verdict.
 *
 *   3. {@see autoPromoteIfEligible} — convenience wrapper used by the
 *      `documents.review` admin endpoint: after a doc gets verified,
 *      check if the company now qualifies for a higher tier and bump
 *      it automatically. Demotion never happens here — that's only
 *      done by the sanctions pipeline.
 */
class VerificationService
{
    /**
     * The highest tier this company currently qualifies for. Returns
     * UNVERIFIED when no documents are verified or when sanctions block
     * any tier upgrade.
     */
    public function eligibleLevel(Company $company): VerificationLevel
    {
        // Sanctions block: a hit or review verdict pins the company to
        // UNVERIFIED regardless of how many documents they have.
        if (in_array($company->sanctions_status, ['hit', 'review'], true)) {
            return VerificationLevel::UNVERIFIED;
        }

        $verifiedTypes = $this->verifiedDocumentTypes($company);
        $beneficialOwnerCount = $company->beneficialOwners()->count();

        // Walk down from PLATINUM to BRONZE; first match wins. We need
        // the descending order so a Gold-eligible company doesn't
        // accidentally settle for Bronze.
        foreach ([
            VerificationLevel::PLATINUM,
            VerificationLevel::GOLD,
            VerificationLevel::SILVER,
            VerificationLevel::BRONZE,
        ] as $tier) {
            if (!$this->satisfies($tier, $verifiedTypes, $beneficialOwnerCount)) {
                continue;
            }
            return $tier;
        }

        return VerificationLevel::UNVERIFIED;
    }

    /**
     * Explicit admin promotion to a specific tier. Returns the updated
     * company. Throws when sanctions block the promotion or when the
     * target tier's requirements aren't met (admins can override docs
     * via the queue UI by setting `force = true`).
     */
    public function promote(
        Company $company,
        VerificationLevel $target,
        int $promotedByUserId,
        bool $force = false,
    ): Company {
        if (in_array($company->sanctions_status, ['hit', 'review'], true) && $target !== VerificationLevel::UNVERIFIED) {
            throw new \RuntimeException('Cannot promote a company with an unresolved sanctions verdict.');
        }

        if (!$force && $target !== VerificationLevel::UNVERIFIED) {
            $verifiedTypes = $this->verifiedDocumentTypes($company);
            $beneficialOwnerCount = $company->beneficialOwners()->count();
            if (!$this->satisfies($target, $verifiedTypes, $beneficialOwnerCount)) {
                throw new \RuntimeException(
                    "Company does not satisfy {$target->value} tier requirements (use \$force=true to override).",
                );
            }
        }

        $company->update([
            'verification_level' => $target,
            'verified_by'        => $promotedByUserId,
            'verified_at'        => now(),
        ]);

        return $company->fresh();
    }

    /**
     * Called from the documents.review endpoint after a document has
     * been verified. Promotes the company to its highest eligible tier
     * if that tier is strictly higher than its current one. Never
     * demotes (sanctions pipeline owns that).
     */
    public function autoPromoteIfEligible(Company $company, int $triggeredByUserId): ?VerificationLevel
    {
        $current  = $company->verification_level ?? VerificationLevel::UNVERIFIED;
        $eligible = $this->eligibleLevel($company);

        if ($eligible->rank() <= $current->rank()) {
            return null;
        }

        $this->promote($company, $eligible, $triggeredByUserId);
        return $eligible;
    }

    /**
     * Whether the given verified document set + beneficial owner count
     * satisfies the target tier's requirements. Encapsulates the
     * "Gold needs UBO disclosure" rule alongside the document checklist.
     *
     * @param  array<int, string>  $verifiedTypes
     */
    private function satisfies(VerificationLevel $tier, array $verifiedTypes, int $beneficialOwnerCount): bool
    {
        $required = $tier->requiredDocumentTypes();
        if (count(array_diff($required, $verifiedTypes)) > 0) {
            return false;
        }
        if ($tier->requiresBeneficialOwners() && $beneficialOwnerCount < 1) {
            return false;
        }
        return true;
    }

    /**
     * The set of distinct document type values currently in VERIFIED
     * status (not expired) for this company. The same trade license
     * uploaded twice only counts once.
     *
     * @return array<int, string>
     */
    private function verifiedDocumentTypes(Company $company): array
    {
        return CompanyDocument::query()
            ->where('company_id', $company->id)
            ->where('status', CompanyDocument::STATUS_VERIFIED)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->pluck('type')
            ->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : (string) $t)
            ->unique()
            ->values()
            ->all();
    }
}
