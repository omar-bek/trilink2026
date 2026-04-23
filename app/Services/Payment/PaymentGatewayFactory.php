<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Resolve a gateway adapter by key. Three families:
     *   - Card processors: stripe, paypal, network, magnati, telr, checkout
     *   - UAE local rails: uaefts, ipi, dda, noqodi, edirham
     *   - Cross-border:    swift_wire
     *
     * Card and wallet gateways have concrete adapters today; the
     * inter-bank UAE rails share the UaeftsGateway skeleton until each
     * CBUAE connection is certified separately.
     */
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            'network', 'magnati', 'telr', 'checkout' => app(NetworkInternationalGateway::class),
            'noqodi', 'edirham' => app(NoqodiGateway::class),
            // AANI is the CBUAE instant-payment alias rail. `ipi` previously
            // fell back to the generic UAEFTS skeleton — we now split AANI
            // onto its own adapter so alias-routed (mobile/email/IBAN)
            // instant payments go through the correct contract.
            'aani' => app(AaniGateway::class),
            'uaefts', 'ipi', 'dda', 'swift_wire' => app(UaeftsGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }
}
