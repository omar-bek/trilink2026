<?php

namespace App\Services\Shipping;

/**
 * Common contract every shipping carrier adapter must implement.
 *
 * Three operations matter for the H1 logistics flow:
 *
 *   - quote(): given an origin/destination/parcel, return rates so the buyer
 *     can pick a carrier before booking. Most carriers expose this.
 *
 *   - createShipment(): book the shipment and get back a tracking number
 *     and label URL. The tracking number gets stored on our Shipment row.
 *
 *   - track(): poll the carrier's API for the latest status events. We
 *     normalise the response so the UI doesn't care which carrier is behind it.
 *
 * Adapters should fail soft: any HTTP error becomes a structured error
 * payload, never an exception that breaks the request lifecycle.
 */
interface CarrierInterface
{
    /**
     * Carrier's stable code (aramex, dhl, fedex, ups, fetchr).
     */
    public function code(): string;

    /**
     * Human-friendly name shown in the UI.
     */
    public function name(): string;

    /**
     * Get a price + transit-time quote.
     *
     * @param  array{origin:array,destination:array,weight_kg:float,parcels:int,declared_value?:float,currency?:string}  $request
     * @return array{success:bool, rates?:array<int,array{service:string,price:float,currency:string,transit_days:int}>, error?:string}
     */
    public function quote(array $request): array;

    /**
     * Book a shipment with the carrier.
     *
     * @param  array{origin:array,destination:array,weight_kg:float,parcels:int,reference?:string,service?:string}  $request
     * @return array{success:bool, tracking_number?:string, label_url?:string, error?:string}
     */
    public function createShipment(array $request): array;

    /**
     * Pull tracking events for an existing shipment.
     *
     * @return array{success:bool, status?:string, events?:array<int,array{at:string,location:?string,description:string,status:string}>, error?:string}
     */
    public function track(string $trackingNumber): array;
}
