<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case OPEN = 'open';
    case UNDER_REVIEW = 'under_review';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
}
