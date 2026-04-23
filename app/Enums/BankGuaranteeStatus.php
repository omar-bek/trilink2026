<?php

namespace App\Enums;

enum BankGuaranteeStatus: string
{
    case PENDING_ISSUANCE = 'pending_issuance';
    case ISSUED = 'issued';
    case LIVE = 'live';
    case CALLED = 'called';
    case REDUCED = 'reduced';
    case RETURNED = 'returned';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::RETURNED, self::EXPIRED, self::CANCELLED], true);
    }

    public function isLive(): bool
    {
        return in_array($this, [self::LIVE, self::ISSUED, self::CALLED, self::REDUCED], true);
    }
}
