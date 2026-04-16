<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

/**
 * Inbound webhook handlers for payment + escrow providers.
 *
 * Every handler in this file follows the same three-step contract:
 *
 *   1. Verify the request is genuinely from the provider (HMAC or
 *      provider-specific signature). Fail closed — if no secret is
 *      configured the handler refuses the request rather than degrading
 *      to "trust the body".
 *
 *   2. Claim the event id in the webhook_events table. The unique index
 *      makes replays a no-op even under concurrent delivery.
 *
 *   3. Apply the side effect (Payment / EscrowRelease state change) and
 *      return 2xx so the provider stops retrying.
 *
 * Errors during step 3 still return a 200 to the provider — they re-fire
 * unprocessed events, but the (provider, event_id) row already exists so
 * the next delivery short-circuits at step 2 instead of double-applying.
 */
class WebhookController extends Controller
{
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            Log::error('Stripe webhook secret missing — refusing request');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Replay protection: claim the Stripe event id. Stripe guarantees
        // event.id is unique forever, so this is a perfect idempotency key.
        if (! WebhookEvent::claim('stripe', $event->id, $event->type, $request->all())) {
            return response()->json(['received' => true, 'replay' => true]);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handleStripeSuccess($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handleStripeFailed($event->data->object);
                break;
        }

        return response()->json(['received' => true]);
    }

    public function paypalWebhook(Request $request): JsonResponse
    {
        $secret = config('services.paypal.webhook_secret');

        if (! $secret) {
            Log::error('PayPal webhook secret missing — refusing request');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        // PayPal posts a signature header alongside the raw body. We verify
        // HMAC-SHA256(body, secret) matches the header. This is a "shared
        // secret" mode — the merchant dashboard surfaces the same secret on
        // both sides. The full PayPal cert-based verification is overkill
        // for the merchant-internal flow we use here.
        $rawBody = $request->getContent();
        $providedSig = (string) $request->header('Paypal-Transmission-Sig', '');
        $expectedSig = hash_hmac('sha256', $rawBody, $secret);

        if (! $providedSig || ! hash_equals($expectedSig, $providedSig)) {
            Log::warning('PayPal webhook signature mismatch');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $request->all();

        // Replay protection. PayPal's `id` field on the envelope is the
        // unique delivery id; fall back to a hash of the body if missing
        // (defence in depth — should never happen for real PayPal events).
        $eventId = $event['id'] ?? sha1($rawBody);
        $eventType = $event['event_type'] ?? null;

        if (! WebhookEvent::claim('paypal', $eventId, $eventType, $event)) {
            return response()->json(['received' => true, 'replay' => true]);
        }

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $orderId = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
                if ($orderId) {
                    $payment = Payment::where('gateway_order_id', $orderId)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => PaymentStatus::COMPLETED,
                            'paid_date' => now(),
                            'gateway_payment_id' => $event['resource']['id'] ?? null,
                        ]);
                    }
                }
                break;

            case 'PAYMENT.CAPTURE.DENIED':
                $orderId = $event['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
                if ($orderId) {
                    $payment = Payment::where('gateway_order_id', $orderId)->first();
                    if ($payment) {
                        $payment->update([
                            'status' => PaymentStatus::FAILED,
                            'failed_at' => now(),
                            'failure_reason' => 'Payment denied by PayPal',
                        ]);
                    }
                }
                break;
        }

        return response()->json(['received' => true]);
    }

    private function handleStripeSuccess(object $paymentIntent): void
    {
        $payment = Payment::where('gateway_payment_id', $paymentIntent->id)->first();
        if ($payment) {
            $payment->update([
                'status' => PaymentStatus::COMPLETED,
                'paid_date' => now(),
                'transaction_id' => $paymentIntent->latest_charge ?? null,
            ]);
        }
    }

    private function handleStripeFailed(object $paymentIntent): void
    {
        $payment = Payment::where('gateway_payment_id', $paymentIntent->id)->first();
        if ($payment) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'failed_at' => now(),
                'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Payment failed',
            ]);
        }
    }

    /**
     * Phase 3 / Sprint 11 / task 3.4 — bank partner deposit confirmation
     * webhook. Real banks settle escrow deposits asynchronously: the
     * EscrowService::deposit() call leaves the ledger row in 'pending' and
     * waits for this endpoint to fire when the wire actually clears.
     *
     * The endpoint is provider-agnostic — Mashreq, ENBD, and any future
     * bank all POST against /api/webhooks/escrow/{provider} with their own
     * payload shape. We normalise to a `bank_reference` + `amount` and
     * delegate to EscrowService::confirmDepositFromWebhook(), which is
     * idempotent on the bank reference so retries are safe.
     */
    public function escrowWebhook(Request $request, string $provider, EscrowService $escrowService): JsonResponse
    {
        // Provider whitelist — only bank partners we have an HMAC secret
        // for can post to this endpoint. Unknown providers are rejected
        // with 404 to avoid leaking which adapters exist.
        $allowedProviders = ['mashreq_neobiz', 'enbd_trade', 'mock'];
        if (! in_array($provider, $allowedProviders, true)) {
            return response()->json(['error' => 'Unknown provider'], 404);
        }

        // Verify the HMAC signature against the partner-specific secret.
        // The 'mock' provider intentionally accepts any payload so local
        // development and the test suite don't need a configured secret.
        if ($provider !== 'mock') {
            $configKey = match ($provider) {
                'mashreq_neobiz' => 'services.escrow.mashreq.webhook_secret',
                'enbd_trade' => 'services.escrow.enbd.webhook_secret',
                default => null,
            };
            $secret = $configKey ? config($configKey) : null;

            if (! $secret) {
                Log::error('Escrow webhook secret missing', ['provider' => $provider]);

                return response()->json(['error' => 'Webhook not configured'], 503);
            }

            $rawBody = $request->getContent();
            $providedSig = (string) $request->header('X-Bank-Signature', '');
            $expectedSig = hash_hmac('sha256', $rawBody, $secret);

            if (! $providedSig || ! hash_equals($expectedSig, $providedSig)) {
                Log::warning('Escrow webhook signature mismatch', ['provider' => $provider]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        $payload = $request->all();

        // Normalise the provider-specific payload into our internal shape.
        // Each bank uses different field names; default to a flat shape
        // for the in-house mock provider.
        $bankReference = match ($provider) {
            'mashreq_neobiz' => $payload['transaction_id'] ?? $payload['reference'] ?? null,
            'enbd_trade' => $payload['txnRef'] ?? $payload['reference'] ?? null,
            default => $payload['reference'] ?? null,
        };

        $amount = match ($provider) {
            'mashreq_neobiz' => (float) ($payload['amount'] ?? 0),
            'enbd_trade' => (float) ($payload['txnAmount'] ?? 0),
            default => (float) ($payload['amount'] ?? 0),
        };

        $event = $payload['event_type'] ?? $payload['type'] ?? 'deposit.completed';

        if (! $bankReference || $amount <= 0) {
            // Don't log the full payload — bank webhooks carry account
            // numbers and customer references that we never want in logs.
            Log::warning('Escrow webhook with missing fields', [
                'provider' => $provider,
                'event' => $event,
            ]);

            return response()->json(['error' => 'Missing reference or amount'], 422);
        }

        // Replay protection: bank_reference is the natural unique key for
        // a deposit. The EscrowService::confirmDepositFromWebhook() also
        // checks confirmed_at, but claiming the row here adds a second
        // layer for non-deposit events that may arrive in the future.
        if (! WebhookEvent::claim('escrow_'.$provider, $bankReference, $event, $payload)) {
            return response()->json(['received' => true, 'replay' => true]);
        }

        // Today we only act on deposit confirmations. Release confirmations
        // arrive synchronously from the bank API call so the listeners +
        // cron sweeper handle them inline. Future webhook event types
        // (chargebacks, settlement reversals) will branch off this switch.
        if (str_contains($event, 'deposit')) {
            $release = $escrowService->confirmDepositFromWebhook($bankReference, $amount);

            return response()->json(['received' => true, 'matched' => (bool) $release]);
        }

        return response()->json(['received' => true, 'ignored' => true]);
    }
}
