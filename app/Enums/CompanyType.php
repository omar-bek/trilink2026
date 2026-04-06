<?php

namespace App\Enums;

enum CompanyType: string
{
    case BUYER = 'buyer';
    case SUPPLIER = 'supplier';
    case LOGISTICS = 'logistics';
    case CLEARANCE = 'clearance';
    case SERVICE_PROVIDER = 'service_provider';
    case GOVERNMENT = 'government';
}
