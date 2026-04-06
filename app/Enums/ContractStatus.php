<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case PENDING_SIGNATURES = 'pending_signatures';
    case SIGNED = 'signed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case TERMINATED = 'terminated';
    case CANCELLED = 'cancelled';
}
