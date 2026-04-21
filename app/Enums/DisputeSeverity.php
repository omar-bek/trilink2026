<?php

namespace App\Enums;

enum DisputeSeverity: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * SLA for the respondent to acknowledge / answer the claim, in days.
     * Critical cases get a tight clock; low-severity disputes give the
     * respondent a full working week.
     */
    public function responseSlaDays(): int
    {
        return match ($this) {
            self::CRITICAL => 2,
            self::HIGH => 3,
            self::MEDIUM => 5,
            self::LOW => 7,
        };
    }

    /**
     * Total SLA to reach resolution, in days.
     */
    public function resolutionSlaDays(): int
    {
        return match ($this) {
            self::CRITICAL => 10,
            self::HIGH => 21,
            self::MEDIUM => 30,
            self::LOW => 45,
        };
    }
}
