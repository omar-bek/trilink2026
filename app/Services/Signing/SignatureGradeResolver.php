<?php

namespace App\Services\Signing;

use App\Enums\CompanyType;
use App\Enums\SignatureGrade;
use App\Models\Company;
use App\Models\Contract;

/**
 * Phase 6 (UAE Compliance Roadmap) — decides what signature grade
 * each contract requires under Federal Decree-Law 46/2021. The
 * resolver is the single source of truth: ContractService::sign uses
 * it to refuse weak signatures, the contract show page uses it to
 * pick the right CTA, and the public verify page uses it to render
 * the legal context.
 *
 * Rules (in priority order — the strictest match wins):
 *
 *   1. Government counterparty           → QUALIFIED
 *      Any contract whose buyer or any party is a government
 *      company. Government tenders categorically refuse Simple
 *      signatures even on small-value contracts.
 *
 *   2. Sensitive category                → QUALIFIED
 *      Real estate / insurance / financial services contracts
 *      require Qualified regardless of value. The keyword list
 *      is config-driven (config('signing.qualified_keywords'))
 *      so legal counsel can amend it without a code change.
 *
 *   3. High-value contract               → ADVANCED minimum
 *      Total value > AED 500,000. The threshold is configurable
 *      via config('signing.advanced_threshold_aed') so different
 *      tenants can tighten it.
 *
 *   4. Default                           → SIMPLE
 *      Ordinary B2B contracts under the threshold with no
 *      government party.
 *
 * The resolver is PURE — it reads from the Contract / Company
 * relationships only, no DB writes, no side effects. Safe to call
 * from anywhere in the request lifecycle and trivial to test.
 */
class SignatureGradeResolver
{
    public const DEFAULT_ADVANCED_THRESHOLD_AED = 500_000;

    /**
     * Return the minimum grade required for a contract.
     */
    public function requiredFor(Contract $contract): SignatureGrade
    {
        if ($this->hasGovernmentParty($contract)) {
            return SignatureGrade::QUALIFIED;
        }

        if ($this->isInQualifiedCategory($contract)) {
            return SignatureGrade::QUALIFIED;
        }

        if ($this->exceedsAdvancedThreshold($contract)) {
            return SignatureGrade::ADVANCED;
        }

        return SignatureGrade::SIMPLE;
    }

    /**
     * Human-readable explanation of WHY a particular grade is
     * required. Surfaced on the sign UI + on the public verify page
     * so the user understands what's being asked of them and an
     * auditor sees the legal trail.
     */
    public function reasonFor(Contract $contract): string
    {
        if ($this->hasGovernmentParty($contract)) {
            return 'Government counterparty — Federal Decree-Law 46/2021 Article 19 requires a Qualified Electronic Signature for any contract where a UAE government entity is a party.';
        }

        if ($this->isInQualifiedCategory($contract)) {
            return 'Sensitive category — real estate, insurance and financial services contracts require a Qualified Electronic Signature regardless of value.';
        }

        if ($this->exceedsAdvancedThreshold($contract)) {
            $threshold = number_format(
                (float) config('signing.advanced_threshold_aed', self::DEFAULT_ADVANCED_THRESHOLD_AED),
                0
            );
            return "High-value contract (above AED {$threshold}) — Federal Decree-Law 46/2021 Article 18 requires an Advanced Electronic Signature.";
        }

        return 'Standard B2B contract — a Simple Electronic Signature satisfies Federal Decree-Law 46/2021 Article 17.';
    }

    private function hasGovernmentParty(Contract $contract): bool
    {
        // Buyer side first — already loaded as a relation in most paths.
        $contract->loadMissing('buyerCompany');
        if ($contract->buyerCompany && $contract->buyerCompany->type === CompanyType::GOVERNMENT) {
            return true;
        }

        // Supplier-side parties from the JSON column.
        $partyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->filter()
            ->all();

        if (empty($partyIds)) {
            return false;
        }

        return Company::query()
            ->whereIn('id', $partyIds)
            ->where('type', CompanyType::GOVERNMENT->value)
            ->exists();
    }

    private function exceedsAdvancedThreshold(Contract $contract): bool
    {
        $threshold = (float) config(
            'signing.advanced_threshold_aed',
            self::DEFAULT_ADVANCED_THRESHOLD_AED
        );
        return (float) ($contract->total_amount ?? 0) > $threshold;
    }

    private function isInQualifiedCategory(Contract $contract): bool
    {
        // The contract's category lives one hop away on the linked
        // RFQ → category. We don't traverse that here because the
        // relationship is optional (Buy-Now contracts have no RFQ).
        // Instead the resolver reads a config-driven keyword list
        // against the contract title — pragmatic and good enough
        // until categories are normalised in Phase 8.
        $keywords = (array) config('signing.qualified_keywords', [
            'real estate', 'property', 'insurance', 'lease', 'tenancy',
            'mortgage', 'securities', 'investment fund',
        ]);

        $title = mb_strtolower((string) ($contract->title ?? ''));
        if ($title === '') {
            return false;
        }
        foreach ($keywords as $kw) {
            if (mb_strpos($title, mb_strtolower($kw)) !== false) {
                return true;
            }
        }
        return false;
    }
}
