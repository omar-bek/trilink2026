<?php

namespace App\Enums;

/**
 * Canonical milestone keys for contracts.payment_schedule[*].milestone and
 * Payment.milestone. Free text was too fragile — the cheque reconciler,
 * escrow auto-release listeners, and retention service all pattern-match
 * on these strings, and a typo ("deliv" instead of "delivery") silently
 * broke the auto-release chain.
 *
 * Note: existing `retention_release` rows stay compatible because the enum
 * value string is the same. New milestones are added here as named.
 */
enum PaymentMilestone: string
{
    case ADVANCE = 'advance';
    case PRODUCTION = 'production';
    case DELIVERY = 'delivery';
    case INSPECTION = 'inspection';
    case FINAL = 'final';
    case RETENTION = 'retention';
    case RETENTION_RELEASE = 'retention_release';
    case LATE_FEE = 'late_fee';
    case CREDIT_NOTE = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::ADVANCE => 'Advance',
            self::PRODUCTION => 'Production',
            self::DELIVERY => 'Delivery',
            self::INSPECTION => 'Inspection',
            self::FINAL => 'Final',
            self::RETENTION => 'Retention (held)',
            self::RETENTION_RELEASE => 'Retention Release',
            self::LATE_FEE => 'Late Fee',
            self::CREDIT_NOTE => 'Credit Note',
        };
    }
}
