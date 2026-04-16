<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function charge(Payment $payment): array
    {
        $intent = PaymentIntent::create([
            'amount' => (int) ($payment->total_amount * 100),
            'currency' => strtolower($payment->currency),
            'metadata' => [
                'payment_id' => $payment->id,
                'contract_id' => $payment->contract_id,
            ],
        ]);

        return [
            'payment_id' => $intent->id,
            'order_id' => null,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
        ];
    }

    public function refund(Payment $payment): array
    {
        $refund = Refund::create([
            'payment_intent' => $payment->gateway_payment_id,
        ]);

        return [
            'refund_id' => $refund->id,
            'status' => $refund->status,
        ];
    }

    public function getStatus(string $paymentId): array
    {
        $intent = PaymentIntent::retrieve($paymentId);

        return [
            'status' => $intent->status,
            'amount' => $intent->amount / 100,
        ];
    }
}
