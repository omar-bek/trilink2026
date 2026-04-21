<?php

namespace App\Enums;

enum DisputeOfferStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case COUNTERED = 'countered';
    case EXPIRED = 'expired';
    case WITHDRAWN = 'withdrawn';

    public function isOpen(): bool
    {
        return $this === self::PENDING;
    }
}
