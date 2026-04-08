<?php

namespace App\Services\Integrations;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 7 — fans out a single platform event to every registered webhook
 * endpoint that's interested in it. Used by listeners hooked into the
 * domain events (ContractSigned, PaymentProcessed, ShipmentDelivered,
 * EscrowEvent, BidAccepted, etc.).
 *
 * Pattern:
 *   1. dispatch($event, $payload, $companyId) is called from a listener.
 *   2. We resolve every active WebhookEndpoint for this company that
 *      subscribes to the event.
 *   3. For each endpoint we create a WebhookDelivery row in 'pending',
 *      sign + POST the payload, and update the row with the response.
 *   4. Failures bump `failure_count` on the endpoint and Log::warning.
 *
 * The dispatcher itself is sync — listeners that want async should be
 * placed on a queue (most domain events already are).
 */
class WebhookDispatcherService
{
    /**
     * Send the event to every endpoint subscribed by the target company.
     */
    public function dispatch(string $event, array $payload, int $companyId): void
    {
        $endpoints = WebhookEndpoint::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->listensTo($event));

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint, $event, $payload);
        }
    }

    /**
     * Deliver one event to one endpoint. Records the attempt in
     * webhook_deliveries regardless of outcome.
     */
    public function deliver(WebhookEndpoint $endpoint, string $event, array $payload): WebhookDelivery
    {
        $body = json_encode([
            'event'      => $event,
            'data'       => $payload,
            'sent_at'    => now()->toIso8601String(),
            'company_id' => $endpoint->company_id,
        ], JSON_UNESCAPED_UNICODE);

        // HMAC-SHA256 over the JSON body. The customer's receiver should
        // recompute the signature with their copy of the secret to verify
        // authenticity. Header name follows the Stripe convention.
        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event'               => $event,
            'payload'             => json_decode($body, true),
            'attempt'             => 1,
            'status'              => WebhookDelivery::STATUS_PENDING,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'         => 'application/json',
                    'X-TriLink-Event'      => $event,
                    'X-TriLink-Signature'  => $signature,
                    'X-TriLink-Delivery-Id'=> (string) $delivery->id,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $isSuccess = $response->successful();

            $delivery->update([
                'response_status' => $response->status(),
                'response_body'   => substr((string) $response->body(), 0, 2000),
                'status'          => $isSuccess ? WebhookDelivery::STATUS_SUCCESS : WebhookDelivery::STATUS_FAILED,
            ]);

            if ($isSuccess) {
                $endpoint->update([
                    'last_delivered_at' => now(),
                    'failure_count'     => 0,
                ]);
            } else {
                $endpoint->increment('failure_count');
                Log::warning('Webhook delivery failed', [
                    'endpoint' => $endpoint->id,
                    'event'    => $event,
                    'status'   => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'status'        => WebhookDelivery::STATUS_FAILED,
                'response_body' => substr($e->getMessage(), 0, 2000),
            ]);
            $endpoint->increment('failure_count');
            Log::warning('Webhook delivery exception', [
                'endpoint' => $endpoint->id,
                'event'    => $event,
                'error'    => $e->getMessage(),
            ]);
        }

        return $delivery->fresh();
    }
}
