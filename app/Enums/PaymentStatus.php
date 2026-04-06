<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
}
