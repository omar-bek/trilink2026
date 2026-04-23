<?php

namespace App\Enums;

/**
 * UAE Federal Tax Authority (FTA) VAT categories. Designated Zones and
 * reverse-charge cases are distinct treatments under Cabinet Decision
 * 52/2017 and must be tracked per contract to generate correct tax
 * invoices and to feed the periodic VAT return.
 */
enum VatTreatment: string
{
    case STANDARD = 'standard';              // 5% VAT
    case ZERO_RATED = 'zero_rated';          // 0% (exports, international transport)
    case EXEMPT = 'exempt';                  // financial, residential lease
    case OUT_OF_SCOPE = 'out_of_scope';      // extraterritorial
    case REVERSE_CHARGE = 'reverse_charge';  // B2B imports, designated zones
    case DESIGNATED_ZONE = 'designated_zone';

    public function ratePct(): float
    {
        return match ($this) {
            self::STANDARD => 5.0,
            default => 0.0,
        };
    }

    public function requiresTrnOnInvoice(): bool
    {
        return in_array($this, [self::STANDARD, self::REVERSE_CHARGE, self::DESIGNATED_ZONE], true);
    }
}
