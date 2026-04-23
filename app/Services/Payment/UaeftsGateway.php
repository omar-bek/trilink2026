<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\SettlementCalendarService;
use Illuminate\Support\Str;

/**
 * UAE Funds Transfer System (UAEFTS) — Central Bank RTGS. Used for
 * high-value same-day wires between UAE banks. Cut-off is 15:00
 * Sun–Thu; anything submitted after cut-off settles next business
 * day. This adapter computes the expected settlement time using the
 * SettlementCalendarService so finance teams see the right promise.
 */
class UaeftsGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly SettlementCalendarService $calendar) {}

    public function charge(Payment $payment): array
    {
        $now = now();
        $cutoff = $now->copy()->setTime(15, 0);
        $settleDate = $now->greaterThan($cutoff)
            ? $this->calendar->nextBusinessDay($now->copy()->addDay())
            : $this->calendar->nextBusinessDay($now);

        return [
            'payment_id' => 'UAEFTS-'.strtoupper(Str::random(14)),
            'order_id' => 'UAEFTS-ORD-'.$payment->id,
            'status' => 'pending',
            'expected_settlement' => $settleDate->toDateString(),
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'refund_id' => 'UAEFTS-R-'.strtoupper(Str::random(12)),
            'status' => 'pending',
        ];
    }

    public function getStatus(string $paymentId): array
    {
        return ['status' => 'completed', 'payment_id' => $paymentId];
    }
}
