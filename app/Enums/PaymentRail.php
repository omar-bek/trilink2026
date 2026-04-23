<?php

namespace App\Enums;

/**
 * Settlement rail. Captures whether the payment moved via a card gateway,
 * a UAE local clearing rail, a SWIFT wire, direct debit, or the internal
 * escrow ledger. Drives the UI's "Paid via…" badge and the recon engine.
 */
enum PaymentRail: string
{
    // Generic card gateways.
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    // UAE card gateways.
    case NETWORK_INTERNATIONAL = 'network';
    case MAGNATI = 'magnati';
    case TELR = 'telr';
    case CHECKOUT = 'checkout';
    // UAE inter-bank rails (CBUAE).
    case UAEFTS = 'uaefts';           // UAE Funds Transfer System (RTGS)
    case IPI = 'ipi';                 // Immediate Payment Instruction
    case AANI = 'aani';               // CBUAE AANI instant-alias payments
    case DDA = 'dda';                 // Direct Debit Authority
    // UAE government / wallet.
    case NOQODI = 'noqodi';
    case EDIRHAM = 'edirham';
    // Cross-border.
    case SWIFT_WIRE = 'swift_wire';
    // Paper + deferred.
    case CHEQUE = 'cheque';                // dated cheque presented today
    case POSTDATED_CHEQUE = 'pdc';         // PDC — UAE staple for B2B
    // Payroll — UAE Wage Protection System.
    case WPS = 'wps';
    // Internal.
    case ESCROW = 'escrow';

    public function isCard(): bool
    {
        return in_array($this, [
            self::STRIPE, self::PAYPAL, self::NETWORK_INTERNATIONAL,
            self::MAGNATI, self::TELR, self::CHECKOUT,
        ], true);
    }

    public function isInstant(): bool
    {
        return in_array($this, [self::IPI, self::AANI, self::NOQODI, self::EDIRHAM], true);
    }

    public function settlementWindowHours(): int
    {
        return match ($this) {
            self::IPI, self::AANI, self::NOQODI, self::EDIRHAM, self::STRIPE, self::CHECKOUT => 1,
            self::UAEFTS, self::WPS => 4,
            self::NETWORK_INTERNATIONAL, self::MAGNATI, self::TELR => 24,
            self::DDA, self::CHEQUE => 48,
            self::PAYPAL, self::SWIFT_WIRE => 72,
            self::POSTDATED_CHEQUE => 720, // ~30d — dominated by presentation_date anyway
            self::ESCROW => 0,
        };
    }

    public function isPaper(): bool
    {
        return in_array($this, [self::CHEQUE, self::POSTDATED_CHEQUE], true);
    }
}
