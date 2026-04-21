<?php

namespace App\Enums;

/**
 * The remedy the claimant is seeking. These map to concrete post-resolution
 * actions: a refund triggers a payment reversal, a replacement triggers a
 * new shipment, a credit note triggers a tax document, etc.
 */
enum DisputeRemedy: string
{
    case REFUND = 'refund';
    case REPLACEMENT = 'replacement';
    case CREDIT_NOTE = 'credit_note';
    case REPAIR = 'repair';
    case PRICE_ADJUSTMENT = 'price_adjustment';
    case CONTRACT_AMENDMENT = 'contract_amendment';
    case OTHER = 'other';
}
