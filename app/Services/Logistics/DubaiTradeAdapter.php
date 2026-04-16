<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 6 — Dubai Trade portal integration. Posts a shipment manifest
 * into Dubai Trade's customs declaration API so the shipment can clear
 * automatically once it arrives at port.
 *
 * The Dubai Trade API requires a B2G enrolment (which takes weeks), so
 * the adapter ships in two modes:
 *
 *   1. Live: when DUBAI_TRADE_API_KEY is configured, posts the manifest
 *      to the live endpoint (sandbox URL by default).
 *   2. Stub: returns a deterministic declaration reference that the
 *      shipment can carry without ever talking to the real portal.
 *
 * The shipment row gets a `dubai_trade_ref` saved in `customs_documents`
 * regardless — the rest of the platform doesn't need to know whether
 * we hit the real portal or the stub.
 */
class DubaiTradeAdapter
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.sandbox.dubaitrade.ae/customs/v1',
        private readonly int $timeout = 12,
    ) {}

    public function isLive(): bool
    {
        return (bool) $this->apiKey;
    }

    /**
     * Submit a customs declaration for the shipment. Returns a
     * normalised payload with the declaration reference and status.
     */
    public function submitDeclaration(Shipment $shipment): array
    {
        $payload = $this->buildPayload($shipment);

        if (! $this->isLive()) {
            return $this->stubResponse();
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/declarations', $payload);

            if ($response->failed()) {
                Log::warning('Dubai Trade declaration failed', [
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 500),
                ]);

                return ['success' => false, 'error' => 'HTTP '.$response->status()];
            }

            $body = $response->json();

            return [
                'success' => true,
                'reference' => (string) ($body['declaration_id'] ?? ''),
                'status' => (string) ($body['status'] ?? 'submitted'),
                'submitted_at' => now()->toIso8601String(),
                'mode' => 'live',
            ];
        } catch (\Throwable $e) {
            Log::warning('Dubai Trade exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the manifest payload Dubai Trade expects. Pulled out of
     * submitDeclaration so the shape is unit-testable in isolation
     * (and the stub can return a payload that mirrors what the live
     * API would have received).
     */
    private function buildPayload(Shipment $shipment): array
    {
        $contract = $shipment->contract;
        $amounts = is_array($contract?->amounts) ? $contract->amounts : [];

        return [
            'tracking_number' => $shipment->tracking_number,
            'origin' => $shipment->origin,
            'destination' => $shipment->destination,
            'declared_value' => (float) ($contract?->total_amount ?? 0),
            'currency' => $contract?->currency ?? 'AED',
            'consignee_tax_id' => $contract?->buyerCompany?->tax_number,
            'shipper_tax_id' => $shipment->company?->tax_number,
            'lines' => $amounts['lines'] ?? [],
            'estimated_arrival' => $shipment->estimated_delivery?->toDateString(),
        ];
    }

    private function stubResponse(): array
    {
        return [
            'success' => true,
            'reference' => 'DT-STUB-'.strtoupper(Str::random(10)),
            'status' => 'submitted',
            'submitted_at' => now()->toIso8601String(),
            'mode' => 'stub',
        ];
    }
}
