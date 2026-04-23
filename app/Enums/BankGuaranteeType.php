<?php

namespace App\Enums;

enum BankGuaranteeType: string
{
    case BID_BOND = 'bid_bond';
    case PERFORMANCE_BOND = 'performance_bond';
    case ADVANCE_PAYMENT = 'advance_payment';
    case RETENTION = 'retention';
    case WARRANTY = 'warranty';
    case LABOR = 'labor';
    case CUSTOM = 'custom';

    /**
     * Typical percentage of the base (bid amount or contract value) that
     * UAE buyers expect for each guarantee type. Used as a default in the
     * RFQ builder so procurement teams don't have to remember the norms.
     */
    public function defaultPercentage(): ?float
    {
        return match ($this) {
            self::BID_BOND => 2.0,
            self::PERFORMANCE_BOND => 10.0,
            self::ADVANCE_PAYMENT => 100.0,
            self::RETENTION => 5.0,
            self::WARRANTY => 5.0,
            default => null,
        };
    }

    /**
     * Whether the BG is expected to reduce as progress is made. Advance
     * Payment BGs reduce proportionally to deliveries; Performance BGs
     * stay at face value until release.
     */
    public function reducible(): bool
    {
        return $this === self::ADVANCE_PAYMENT;
    }
}
