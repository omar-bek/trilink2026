<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

/**
 * Shared scaffolding for carrier adapters. Two reasons for the abstract:
 *
 *   1. Each adapter has a "live mode" (real HTTP call to the carrier) and a
 *      "mock mode" (deterministic synthetic response). The mode is decided
 *      by whether the carrier's API credentials are set in config. Until
 *      every customer has commercial accounts with every carrier, mock mode
 *      keeps the platform demo-able end-to-end.
 *
 *   2. The mock implementation is identical across carriers — it generates
 *      a deterministic price + transit time from the parcel weight + a
 *      tracking number prefixed with the carrier code. Putting it once
 *      here means a new carrier just needs to implement the live path.
 */
abstract class AbstractCarrier implements CarrierInterface
{
    public function __construct(protected readonly array $config = [])
    {
    }

    abstract public function code(): string;

    abstract public function name(): string;

    /**
     * True when the adapter has the credentials it needs for live API calls.
     * Subclasses override to check whichever keys their carrier expects.
     */
    protected function isLive(): bool
    {
        return false;
    }

    public function quote(array $request): array
    {
        if (!$this->isLive()) {
            return $this->mockQuote($request);
        }
        try {
            return $this->liveQuote($request);
        } catch (\Throwable $e) {
            Log::warning("[carrier:{$this->code()}] quote failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createShipment(array $request): array
    {
        if (!$this->isLive()) {
            return $this->mockCreateShipment($request);
        }
        try {
            return $this->liveCreateShipment($request);
        } catch (\Throwable $e) {
            Log::warning("[carrier:{$this->code()}] createShipment failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function track(string $trackingNumber): array
    {
        if (!$this->isLive()) {
            return $this->mockTrack($trackingNumber);
        }
        try {
            return $this->liveTrack($trackingNumber);
        } catch (\Throwable $e) {
            Log::warning("[carrier:{$this->code()}] track failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Subclasses override these for the real carrier API.
    protected function liveQuote(array $request): array { return $this->mockQuote($request); }
    protected function liveCreateShipment(array $request): array { return $this->mockCreateShipment($request); }
    protected function liveTrack(string $trackingNumber): array { return $this->mockTrack($trackingNumber); }

    /**
     * Deterministic synthetic quote. Pricing model: a flat carrier base
     * fee + per-kg rate + small randomness from the destination string so
     * different routes get different prices. Same input = same output.
     */
    protected function mockQuote(array $request): array
    {
        $weight   = max(0.1, (float) ($request['weight_kg'] ?? 1));
        $parcels  = max(1, (int) ($request['parcels'] ?? 1));
        $currency = $request['currency'] ?? 'AED';
        $base     = $this->mockBaseFee();
        $perKg    = $this->mockPerKgRate();
        $routeMod = (crc32(json_encode($request['destination'] ?? [])) % 50) / 10;

        $standard = round($base + ($perKg * $weight * $parcels) + $routeMod, 2);
        $express  = round($standard * 1.6, 2);

        return [
            'success' => true,
            'rates' => [
                [
                    'service'      => 'standard',
                    'price'        => $standard,
                    'currency'     => $currency,
                    'transit_days' => $this->mockTransitDays('standard'),
                ],
                [
                    'service'      => 'express',
                    'price'        => $express,
                    'currency'     => $currency,
                    'transit_days' => $this->mockTransitDays('express'),
                ],
            ],
        ];
    }

    protected function mockCreateShipment(array $request): array
    {
        // A deterministic-looking tracking number prefixed with the carrier.
        // Real carriers use their own format; this is enough for the UI to
        // round-trip end-to-end during demos.
        return [
            'success'         => true,
            'tracking_number' => strtoupper($this->code()) . '-' . strtoupper(substr(uniqid(), -10)),
            'label_url'       => null,
        ];
    }

    protected function mockTrack(string $trackingNumber): array
    {
        return [
            'success' => true,
            'status'  => 'in_transit',
            'events'  => [
                [
                    'at'          => now()->subDays(2)->toIso8601String(),
                    'location'    => 'Origin warehouse',
                    'description' => 'Picked up by ' . $this->name(),
                    'status'      => 'picked_up',
                ],
                [
                    'at'          => now()->subDay()->toIso8601String(),
                    'location'    => 'Sorting facility',
                    'description' => 'Departed sorting facility',
                    'status'      => 'in_transit',
                ],
                [
                    'at'          => now()->toIso8601String(),
                    'location'    => 'Destination hub',
                    'description' => 'Arrived at destination hub',
                    'status'      => 'in_transit',
                ],
            ],
        ];
    }

    // Per-carrier base fees. Subclasses override to differentiate quotes.
    protected function mockBaseFee(): float { return 25.0; }
    protected function mockPerKgRate(): float { return 5.0; }
    protected function mockTransitDays(string $service): int
    {
        return $service === 'express' ? 2 : 5;
    }
}
