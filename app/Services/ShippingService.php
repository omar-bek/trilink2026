<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Services\Shipping\CarrierFactory;

/**
 * High-level facade over the carrier adapters. Application code (controllers,
 * jobs) talks to this service; the carrier-specific HTTP details stay
 * inside Services/Shipping.
 *
 * Two main flows:
 *   - quoteAll(): ask every carrier for a price and return the merged
 *     leaderboard. Used on the "Get rates" UI.
 *   - syncTracking(): pull the latest events for an existing shipment and
 *     mirror them into our tracking_events table so the buyer can see the
 *     timeline without leaving the platform.
 */
class ShippingService
{
    public function __construct(private readonly CarrierFactory $factory) {}

    /**
     * Ask every carrier for a quote and return the flattened, sorted list
     * of rates. Failed carriers are silently dropped from the list — the
     * buyer just sees fewer options, not an error wall.
     *
     * @return array<int, array{carrier:string,carrier_name:string,service:string,price:float,currency:string,transit_days:int}>
     */
    public function quoteAll(array $request): array
    {
        $all = [];
        foreach ($this->factory->all() as $carrier) {
            $result = $carrier->quote($request);
            if (! ($result['success'] ?? false)) {
                continue;
            }
            foreach ($result['rates'] ?? [] as $rate) {
                $all[] = array_merge($rate, [
                    'carrier' => $carrier->code(),
                    'carrier_name' => $carrier->name(),
                ]);
            }
        }

        usort($all, fn ($a, $b) => $a['price'] <=> $b['price']);

        return $all;
    }

    /**
     * Quote a single carrier (used when the buyer has already picked one).
     */
    public function quote(string $carrierCode, array $request): array
    {
        return $this->factory->make($carrierCode)->quote($request);
    }

    /**
     * Pull the latest tracking events for a Shipment and persist any new
     * ones into tracking_events. Idempotent — if we've already stored an
     * event with the same `at` + `description`, it's skipped.
     *
     * Returns the count of newly inserted events.
     */
    public function syncTracking(Shipment $shipment, string $carrierCode): int
    {
        if (! $shipment->tracking_number) {
            return 0;
        }

        $carrier = $this->factory->make($carrierCode);
        $result = $carrier->track($shipment->tracking_number);

        if (! ($result['success'] ?? false)) {
            return 0;
        }

        $inserted = 0;
        foreach ($result['events'] ?? [] as $event) {
            $exists = TrackingEvent::where('shipment_id', $shipment->id)
                ->where('event_at', $event['at'])
                ->where('description', $event['description'])
                ->exists();
            if ($exists) {
                continue;
            }

            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_at' => $event['at'],
                'location' => $event['location'] ?? null,
                'description' => $event['description'],
                'status' => $event['status'] ?? null,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Book a shipment with a chosen carrier and persist the tracking number
     * back onto the existing Shipment row.
     */
    public function bookShipment(Shipment $shipment, string $carrierCode, array $request): array
    {
        $carrier = $this->factory->make($carrierCode);
        $result = $carrier->createShipment($request);

        if ($result['success'] ?? false) {
            $shipment->update([
                'tracking_number' => $result['tracking_number'],
            ]);
        }

        return $result;
    }
}
