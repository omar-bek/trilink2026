<?php

namespace App\Enums;

enum DisputeType: string
{
    case QUALITY = 'quality';
    case DELIVERY = 'delivery';
    case PAYMENT = 'payment';
    case CONTRACT_BREACH = 'contract_breach';
    case OTHER = 'other';
}
