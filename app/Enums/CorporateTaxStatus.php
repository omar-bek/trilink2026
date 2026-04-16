<?php

namespace App\Enums;

enum CorporateTaxStatus: string
{
    case REGISTERED = 'registered';
    case QFZP = 'qfzp';
    case EXEMPT_BELOW_THRESHOLD = 'exempt_below_threshold';
    case NOT_REGISTERED = 'not_registered';
}
