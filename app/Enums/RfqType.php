<?php

namespace App\Enums;

enum RfqType: string
{
    case SUPPLIER = 'supplier';
    case LOGISTICS = 'logistics';
    case CLEARANCE = 'clearance';
    case SERVICE_PROVIDER = 'service_provider';

    /**
     * A sales-side RFQ — the publishing company is the one selling, and the
     * "bids" submitted on it are purchase offers from buyers. Used by the
     * sales team to surface inventory/services to other companies.
     */
    case SALES_OFFER = 'sales_offer';

    /**
     * Whether bids on this type are submitted by the BUYING side. For
     * SALES_OFFER, the company posting the RFQ is the seller and the bidder
     * is the buyer — the inverse of all other types.
     */
    public function bidderIsBuyer(): bool
    {
        return $this === self::SALES_OFFER;
    }
}
