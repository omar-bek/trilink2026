<?php

namespace App\Enums;

/**
 * Trust tier shown next to a company everywhere it appears on the platform.
 *
 * The hierarchy:
 *   - UNVERIFIED: just registered, no docs uploaded yet
 *   - BRONZE:     basic registration confirmed (trade license uploaded)
 *   - SILVER:     KYB done — trade license + tax cert + ID verified by admin
 *   - GOLD:       audited financials + insurance + 3rd-party verification
 *   - PLATINUM:   credit rating + ESG score + multi-year platform history
 *
 * Higher tiers unlock perks: bigger transaction limits, lower escrow fees,
 * priority discovery placement, eligibility for trade finance.
 */
enum VerificationLevel: string
{
    case UNVERIFIED = 'unverified';
    case BRONZE = 'bronze';
    case SILVER = 'silver';
    case GOLD = 'gold';
    case PLATINUM = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => __('trust.level_unverified'),
            self::BRONZE => __('trust.level_bronze'),
            self::SILVER => __('trust.level_silver'),
            self::GOLD => __('trust.level_gold'),
            self::PLATINUM => __('trust.level_platinum'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'zinc',
            self::BRONZE => 'amber',
            self::SILVER => 'slate',
            self::GOLD => 'yellow',
            self::PLATINUM => 'violet',
        };
    }

    /**
     * Numeric ordering — useful for "this RFQ requires Silver or above"
     * type comparisons.
     */
    public function rank(): int
    {
        return match ($this) {
            self::UNVERIFIED => 0,
            self::BRONZE => 1,
            self::SILVER => 2,
            self::GOLD => 3,
            self::PLATINUM => 4,
        };
    }

    /**
     * Phase 2 / Sprint 8 / task 2.5 — required document types per tier.
     *
     * The verification queue (and the auto-promotion service) consult this
     * map to decide which tier a company qualifies for. Each entry is the
     * minimum set of {@see DocumentType} values that must be present and
     * verified for the tier to be granted.
     *
     * Higher tiers inherit lower-tier requirements: a Gold-eligible
     * company must also satisfy the Bronze + Silver lists.
     *
     * @return array<int, string>
     */
    public function requiredDocumentTypes(): array
    {
        return match ($this) {
            self::BRONZE => ['trade_license'],
            self::SILVER => ['trade_license', 'tax_certificate', 'company_profile'],
            self::GOLD => [
                'trade_license',
                'tax_certificate',
                'company_profile',
                'audited_financials',
                'insurance_certificate',
            ],
            self::PLATINUM => [
                'trade_license',
                'tax_certificate',
                'company_profile',
                'audited_financials',
                'insurance_certificate',
                'iso_9001',
            ],
            default => [],
        };
    }

    /**
     * Whether reaching this tier additionally requires the company to
     * disclose at least one beneficial owner. Driven by AML / UAE PDPL
     * compliance: Gold and above need at least one disclosed UBO.
     */
    public function requiresBeneficialOwners(): bool
    {
        return $this->rank() >= self::GOLD->rank();
    }
}
