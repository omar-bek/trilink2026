<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Noqodi — government-adjacent UAE payment gateway. Mandatory whenever
 * the counterparty is a federal or emirate government entity. Stub
 * implementation returns deterministic references until a production
 * Noqodi merchant account is provisioned.
 */
class NoqodiGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment): array
    {
        return [
            'payment_id' => 'NOQ-'.strtoupper(Str::random(14)),
            'order_id' => 'NOQ-ORD-'.$payment->id,
            'status' => 'pending',
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'refund_id' => 'NOQ-R-'.strtoupper(Str::random(12)),
            'status' => 'completed',
        ];
    }

    public function getStatus(string $paymentId): array
    {
        return ['status' => 'completed', 'payment_id' => $paymentId];
    }
}
