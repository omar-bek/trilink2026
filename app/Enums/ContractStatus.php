<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    // High-value contracts (above the buyer company's
    // approval_threshold_aed) wait here for an internal approver
    // before being released to the counter-party for signature.
    case PENDING_INTERNAL_APPROVAL = 'pending_internal_approval';
    case PENDING_SIGNATURES = 'pending_signatures';
    case SIGNED = 'signed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case TERMINATED = 'terminated';
    case CANCELLED = 'cancelled';
}
