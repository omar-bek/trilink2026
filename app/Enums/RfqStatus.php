<?php

namespace App\Enums;

enum RfqStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
