<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalGateway implements PaymentGatewayInterface
{
    private PayPalClient $client;

    public function __construct()
    {
        $this->client = new PayPalClient;
        $this->client->setApiCredentials(config('paypal'));
        $this->client->getAccessToken();
    }

    public function charge(Payment $payment): array
    {
        $order = $this->client->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $payment->currency,
                        'value' => number_format($payment->total_amount, 2, '.', ''),
                    ],
                    'reference_id' => (string) $payment->id,
                ],
            ],
        ]);

        return [
            'payment_id' => null,
            'order_id' => $order['id'] ?? null,
            'approval_url' => collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null,
            'status' => $order['status'] ?? 'CREATED',
        ];
    }

    public function refund(Payment $payment): array
    {
        $result = $this->client->refundCapturedPayment($payment->gateway_payment_id, [
            'amount' => [
                'currency_code' => $payment->currency,
                'value' => number_format($payment->total_amount, 2, '.', ''),
            ],
        ]);

        return [
            'refund_id' => $result['id'] ?? null,
            'status' => $result['status'] ?? 'UNKNOWN',
        ];
    }

    public function getStatus(string $paymentId): array
    {
        $order = $this->client->showOrderDetails($paymentId);

        return [
            'status' => $order['status'] ?? 'UNKNOWN',
            'amount' => $order['purchase_units'][0]['amount']['value'] ?? 0,
        ];
    }
}
