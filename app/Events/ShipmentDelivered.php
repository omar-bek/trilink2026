<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3 / Sprint 12 / task 3.7 — fired by ShipmentService the moment
 * a shipment's status flips to `delivered`. The ReleaseEscrowOnDelivery
 * listener picks it up and releases any payment milestone whose
 * release_condition is `on_delivery` for the shipment's contract.
 *
 * Distinct from ShipmentUpdated/ShipmentLocationUpdated because we only
 * care about the single terminal transition for trade-finance purposes
 * — no need to wake the listener on every GPS ping.
 */
class ShipmentDelivered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Shipment $shipment)
    {
    }
}
