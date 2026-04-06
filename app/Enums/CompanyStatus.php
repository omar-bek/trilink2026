<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}
