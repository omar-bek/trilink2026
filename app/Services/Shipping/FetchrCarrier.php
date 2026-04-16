<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;

/**
 * Fetchr adapter — last-mile specialist focused on the GCC. Strong on
 * intra-UAE and intra-KSA same-day; not used for cross-border. Live mode
 * hits Fetchr's bearer-token REST API when FETCHR_API_KEY is configured.
 */
class FetchrCarrier extends AbstractCarrier
{
    public function code(): string
    {
        return 'fetchr';
    }

    public function name(): string
    {
        return 'Fetchr';
    }

    protected function isLive(): bool
    {
        return ! empty($this->config['api_key']);
    }

    protected function liveQuote(array $request): array
    {
        $endpoint = $this->config['rate_endpoint'] ?? 'https://api.fetchr.us/v3/rate';

        $response = Http::timeout(10)
            ->withToken($this->config['api_key'])
            ->post($endpoint, [
                'origin' => $request['origin'] ?? [],
                'destination' => $request['destination'] ?? [],
                'weight' => $request['weight_kg'] ?? 1,
                'parcels' => $request['parcels'] ?? 1,
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Fetchr API HTTP '.$response->status()];
        }

        $body = $response->json();
        $rates = [];
        foreach ($body['rates'] ?? [] as $r) {
            $rates[] = [
                'service' => $r['service'] ?? 'standard',
                'price' => (float) ($r['amount'] ?? 0),
                'currency' => $r['currency'] ?? 'AED',
                'transit_days' => (int) ($r['transit_days'] ?? 1),
            ];
        }

        return $rates ? ['success' => true, 'rates' => $rates] : $this->mockQuote($request);
    }

    protected function mockBaseFee(): float
    {
        return 18.0;
    }

    protected function mockPerKgRate(): float
    {
        return 3.5;
    }

    protected function mockTransitDays(string $service): int
    {
        return $service === 'express' ? 0 : 2; // Same-day in express mode
    }
}
