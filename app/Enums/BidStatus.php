<?php

namespace App\Enums;

enum BidStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
}
