<?php

namespace App\Enums;

/**
 * Canonical document types accepted into the company Document Vault.
 *
 * Keep this list intentionally small. New types should map to a verification
 * tier requirement so the rest of the trust system can reason about them.
 */
enum DocumentType: string
{
    case TRADE_LICENSE = 'trade_license';
    case TAX_CERTIFICATE = 'tax_certificate';
    case COMPANY_PROFILE = 'company_profile';
    case ISO_9001 = 'iso_9001';
    case ISO_14001 = 'iso_14001';
    case ISO_45001 = 'iso_45001';
    case AUDITED_FINANCIALS = 'audited_financials';
    case BANK_LETTER = 'bank_letter';
    case INSURANCE_CERTIFICATE = 'insurance_certificate';
    case HALAL_CERTIFICATE = 'halal_certificate';
    case CE_CERTIFICATE = 'ce_certificate';
    case FDA_REGISTRATION = 'fda_registration';
    case OTHER = 'other';

    public function label(): string
    {
        return __('trust.doc_'.$this->value);
    }

    /**
     * Whether this document type is required for the given verification tier.
     */
    public static function requiredFor(VerificationLevel $level): array
    {
        return match ($level) {
            VerificationLevel::BRONZE => [self::TRADE_LICENSE],
            VerificationLevel::SILVER => [self::TRADE_LICENSE, self::TAX_CERTIFICATE],
            VerificationLevel::GOLD => [self::TRADE_LICENSE, self::TAX_CERTIFICATE, self::AUDITED_FINANCIALS, self::INSURANCE_CERTIFICATE],
            default => [],
        };
    }
}
