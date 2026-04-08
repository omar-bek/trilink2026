<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * FedEx adapter. Live mode hits the FedEx REST API (OAuth2) when
 * FEDEX_CLIENT_ID + FEDEX_CLIENT_SECRET + FEDEX_ACCOUNT are configured.
 *
 * Token caching is built in — FedEx OAuth tokens last 1h so we cache for
 * 50 minutes to stay safely under the expiry.
 */
class FedExCarrier extends AbstractCarrier
{
    public function code(): string { return 'fedex'; }
    public function name(): string { return 'FedEx'; }

    protected function isLive(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    protected function liveQuote(array $request): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'FedEx OAuth failed'];
        }

        $endpoint = $this->config['rate_endpoint'] ?? 'https://apis.fedex.com/rate/v1/rates/quotes';

        $response = Http::timeout(10)
            ->withToken($token)
            ->post($endpoint, [
                'accountNumber' => ['value' => $this->config['account'] ?? ''],
                'requestedShipment' => [
                    'shipper'        => $this->mapAddress($request['origin'] ?? []),
                    'recipient'      => $this->mapAddress($request['destination'] ?? []),
                    'pickupType'     => 'DROPOFF_AT_FEDEX_LOCATION',
                    'rateRequestType'=> ['LIST', 'ACCOUNT'],
                    'requestedPackageLineItems' => [[
                        'weight' => ['units' => 'KG', 'value' => $request['weight_kg'] ?? 1],
                    ]],
                ],
            ]);

        if (!$response->successful()) {
            return ['success' => false, 'error' => 'FedEx API HTTP ' . $response->status()];
        }

        $body  = $response->json();
        $rates = [];
        foreach ($body['output']['rateReplyDetails'] ?? [] as $detail) {
            $rates[] = [
                'service'      => $detail['serviceType'] ?? 'standard',
                'price'        => (float) ($detail['ratedShipmentDetails'][0]['totalNetCharge'] ?? 0),
                'currency'     => $detail['ratedShipmentDetails'][0]['currency'] ?? 'USD',
                'transit_days' => (int) ($detail['operationalDetail']['transitTime'] ?? 3),
            ];
        }

        return $rates ? ['success' => true, 'rates' => $rates] : $this->mockQuote($request);
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('carrier:fedex:token', now()->addMinutes(50), function () {
            $endpoint = $this->config['oauth_endpoint'] ?? 'https://apis.fedex.com/oauth/token';
            $response = Http::asForm()->post($endpoint, [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ]);
            return $response->successful() ? ($response->json('access_token')) : null;
        });
    }

    private function mapAddress(array $address): array
    {
        return [
            'address' => [
                'streetLines' => [$address['address'] ?? ''],
                'city'        => $address['city'] ?? '',
                'postalCode'  => $address['post_code'] ?? '',
                'countryCode' => strtoupper($address['country'] ?? 'AE'),
            ],
        ];
    }

    protected function mockBaseFee(): float { return 50.0; }
    protected function mockPerKgRate(): float { return 7.5; }
    protected function mockTransitDays(string $service): int
    {
        return $service === 'express' ? 2 : 4;
    }
}
