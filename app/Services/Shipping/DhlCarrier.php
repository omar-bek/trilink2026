<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;

/**
 * DHL Express adapter. Live mode hits the DHL MyDHL API REST gateway when
 * DHL_API_KEY + DHL_API_SECRET + DHL_ACCOUNT are configured. DHL is the
 * fastest international option for ME→Europe/US lanes which the mock
 * pricing reflects with a higher base + lower per-kg rate.
 */
class DhlCarrier extends AbstractCarrier
{
    public function code(): string
    {
        return 'dhl';
    }

    public function name(): string
    {
        return 'DHL Express';
    }

    protected function isLive(): bool
    {
        return ! empty($this->config['api_key']) && ! empty($this->config['api_secret']);
    }

    protected function liveQuote(array $request): array
    {
        $endpoint = $this->config['rate_endpoint'] ?? 'https://express.api.dhl.com/mydhlapi/rates';

        $response = Http::timeout(10)
            ->withBasicAuth($this->config['api_key'], $this->config['api_secret'])
            ->get($endpoint, [
                'accountNumber' => $this->config['account'] ?? '',
                'originCountryCode' => $request['origin']['country'] ?? 'AE',
                'originPostalCode' => $request['origin']['post_code'] ?? '',
                'originCityName' => $request['origin']['city'] ?? '',
                'destinationCountryCode' => $request['destination']['country'] ?? 'AE',
                'destinationPostalCode' => $request['destination']['post_code'] ?? '',
                'destinationCityName' => $request['destination']['city'] ?? '',
                'weight' => $request['weight_kg'] ?? 1,
                'unitOfMeasurement' => 'metric',
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'DHL API HTTP '.$response->status()];
        }

        $body = $response->json();
        $rates = [];
        foreach ($body['products'] ?? [] as $product) {
            $rates[] = [
                'service' => $product['productCode'] ?? 'standard',
                'price' => (float) ($product['totalPrice'][0]['price'] ?? 0),
                'currency' => $product['totalPrice'][0]['priceCurrency'] ?? 'USD',
                'transit_days' => (int) ($product['deliveryCapabilities']['totalTransitDays'] ?? 3),
            ];
        }

        return $rates ? ['success' => true, 'rates' => $rates] : $this->mockQuote($request);
    }

    protected function mockBaseFee(): float
    {
        return 45.0;
    }

    protected function mockPerKgRate(): float
    {
        return 8.0;
    }

    protected function mockTransitDays(string $service): int
    {
        return $service === 'express' ? 1 : 3;
    }
}
