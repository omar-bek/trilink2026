<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }
}
