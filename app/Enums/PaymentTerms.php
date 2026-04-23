<?php

namespace App\Enums;

/**
 * Commercial payment terms commonly used in UAE B2B. The days() method
 * returns how long after invoice issue the payment is due; special
 * cases (EOM, early-discount) are handled by PaymentTermsService.
 */
enum PaymentTerms: string
{
    case COD = 'cod';
    case NET_15 = 'net_15';
    case NET_30 = 'net_30';
    case NET_45 = 'net_45';
    case NET_60 = 'net_60';
    case NET_90 = 'net_90';
    case EOM_30 = 'eom_30';           // end-of-month + 30 days
    case TWO_TEN_NET_30 = '2_10_net_30'; // 2% discount if paid in 10 days, else net 30
    case CUSTOM = 'custom';

    public function days(): int
    {
        return match ($this) {
            self::COD => 0,
            self::NET_15 => 15,
            self::NET_30, self::TWO_TEN_NET_30 => 30,
            self::NET_45 => 45,
            self::NET_60 => 60,
            self::NET_90 => 90,
            self::EOM_30 => 30,
            self::CUSTOM => 30,
        };
    }

    public function earlyDiscountRate(): ?float
    {
        return $this === self::TWO_TEN_NET_30 ? 2.0 : null;
    }

    public function earlyDiscountDays(): ?int
    {
        return $this === self::TWO_TEN_NET_30 ? 10 : null;
    }

    public function isEndOfMonth(): bool
    {
        return $this === self::EOM_30;
    }
}
