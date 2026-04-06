<?php

namespace App\Enums;

enum FileCategory: string
{
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case CONTRACT = 'contract';
    case INVOICE = 'invoice';
    case CUSTOMS = 'customs';
    case COMPLIANCE = 'compliance';
    case OTHER = 'other';
}
