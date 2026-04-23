<?php

namespace App\Enums;

/**
 * Post-dated cheque lifecycle. UAE B2B commonly settles via PDCs issued
 * weeks or months in advance; the presentation date + status drives the
 * reconciler, the overdue chaser, and the dishonoured-cheque dispute
 * workflow (Article 401 UAE Penal Code prior to the 2022 reform).
 */
enum ChequeStatus: string
{
    case ISSUED = 'issued';            // drawn by issuer, handed to beneficiary
    case DEPOSITED = 'deposited';      // beneficiary deposited with their bank
    case CLEARED = 'cleared';          // funds moved; settles the linked Payment
    case RETURNED = 'returned';        // bounced — insufficient funds, wrong signature, etc.
    case STOPPED = 'stopped';          // issuer instructed bank to stop
    case REPLACED = 'replaced';        // superseded by a later cheque
    case CANCELLED = 'cancelled';      // voided before presentation

    public function isOpen(): bool
    {
        return in_array($this, [self::ISSUED, self::DEPOSITED], true);
    }

    public function isDishonoured(): bool
    {
        return in_array($this, [self::RETURNED, self::STOPPED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Issued',
            self::DEPOSITED => 'Deposited',
            self::CLEARED => 'Cleared',
            self::RETURNED => 'Returned (Bounced)',
            self::STOPPED => 'Stopped',
            self::REPLACED => 'Replaced',
            self::CANCELLED => 'Cancelled',
        };
    }
}
