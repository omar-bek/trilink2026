<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
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
        $event = $request->all();

        switch ($event['event_type'] ?? '') {
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
}
