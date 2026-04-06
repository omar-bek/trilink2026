<?php

namespace App\Enums;

enum RfqType: string
{
    case SUPPLIER = 'supplier';
    case LOGISTICS = 'logistics';
    case CLEARANCE = 'clearance';
    case SERVICE_PROVIDER = 'service_provider';
}
