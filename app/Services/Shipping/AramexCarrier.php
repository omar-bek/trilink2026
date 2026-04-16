<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;

/**
 * Aramex adapter — the regional default for GCC shipments. Live mode hits
 * the Aramex SOAP→JSON gateway when ARAMEX_USERNAME + ARAMEX_PASSWORD +
 * ARAMEX_ACCOUNT_NUMBER are configured. Without those it falls back to
 * the deterministic mock so demos work end-to-end.
 *
 * Aramex is structurally cheaper for intra-GCC and pricier for far Asia,
 * which the mock pricing reflects.
 */
class AramexCarrier extends AbstractCarrier
{
    public function code(): string
    {
        return 'aramex';
    }

    public function name(): string
    {
        return 'Aramex';
    }

    protected function isLive(): bool
    {
        return ! empty($this->config['username'])
            && ! empty($this->config['password'])
            && ! empty($this->config['account_number']);
    }

    protected function liveQuote(array $request): array
    {
        // Aramex Rate Calculator API expects a strict envelope with account
        // credentials, origin/destination addresses, and product group.
        // Documented at https://www.aramex.com/developers
        $endpoint = $this->config['rate_endpoint'] ?? 'https://ws.aramex.net/ShippingAPI.V2/RateCalculator/Service_1_0.svc/json/CalculateRate';

        $response = Http::timeout(10)->post($endpoint, [
            'ClientInfo' => [
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
                'AccountNumber' => $this->config['account_number'],
                'AccountPin' => $this->config['account_pin'] ?? '',
                'AccountEntity' => $this->config['account_entity'] ?? 'DXB',
                'AccountCountryCode' => $this->config['account_country'] ?? 'AE',
                'Version' => 'v1.0',
            ],
            'OriginAddress' => $this->mapAddress($request['origin'] ?? []),
            'DestinationAddress' => $this->mapAddress($request['destination'] ?? []),
            'ShipmentDetails' => [
                'NumberOfPieces' => $request['parcels'] ?? 1,
                'ActualWeight' => ['Value' => $request['weight_kg'] ?? 1, 'Unit' => 'KG'],
                'ProductGroup' => $request['international'] ?? false ? 'EXP' : 'DOM',
                'ProductType' => 'PPX',
                'PaymentType' => 'P',
            ],
        ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Aramex API HTTP '.$response->status()];
        }

        $body = $response->json();
        $total = $body['TotalAmount']['Value'] ?? null;
        if ($total === null) {
            return $this->mockQuote($request);
        }

        return [
            'success' => true,
            'rates' => [[
                'service' => 'standard',
                'price' => (float) $total,
                'currency' => $body['TotalAmount']['CurrencyCode'] ?? 'AED',
                'transit_days' => 5,
            ]],
        ];
    }

    private function mapAddress(array $address): array
    {
        return [
            'Line1' => $address['address'] ?? '',
            'City' => $address['city'] ?? '',
            'CountryCode' => strtoupper($address['country'] ?? 'AE'),
            'PostCode' => $address['post_code'] ?? '',
        ];
    }

    protected function mockBaseFee(): float
    {
        return 22.0;
    }

    protected function mockPerKgRate(): float
    {
        return 4.5;
    }

    protected function mockTransitDays(string $service): int
    {
        return $service === 'express' ? 1 : 3;
    }
}
