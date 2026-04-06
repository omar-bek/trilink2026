<?php

namespace App\Services\Payment;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function charge(Payment $payment): array;
    public function refund(Payment $payment): array;
    public function getStatus(string $paymentId): array;
}
