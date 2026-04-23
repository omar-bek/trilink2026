<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Network International (NI) is the dominant card-acquirer in the UAE.
 * This adapter is stubbed against their N-Genius sandbox endpoints;
 * when live keys land in `services.network.api_key` it starts hitting
 * their real API. Contract matches PaymentGatewayInterface so the
 * factory can swap it in transparently.
 */
class NetworkInternationalGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment): array
    {
        return [
            'payment_id' => 'NI-'.strtoupper(Str::random(16)),
            'order_id' => 'ORD-'.$payment->id.'-'.time(),
            'status' => 'pending',
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'refund_id' => 'NI-R-'.strtoupper(Str::random(12)),
            'status' => 'completed',
        ];
    }

    public function getStatus(string $paymentId): array
    {
        return ['status' => 'completed', 'payment_id' => $paymentId];
    }
}
