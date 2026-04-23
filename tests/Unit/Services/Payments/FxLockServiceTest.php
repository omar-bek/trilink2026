<?php

namespace Tests\Unit\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\FxLockService;
use Tests\TestCase;

class FxLockServiceTest extends TestCase
{
    public function test_aed_payment_snapshots_rate_one(): void
    {
        $service = new FxLockService();
        $payment = new Payment();
        $payment->amount = 10000;
        $payment->currency = 'AED';

        $service->lock($payment);

        $this->assertSame(1.0, (float) $payment->fx_rate_snapshot);
        $this->assertSame('AED', $payment->fx_base_currency);
        $this->assertNotNull($payment->fx_locked_at);
        $this->assertSame(10000.0, (float) $payment->amount_in_base);
    }

    public function test_already_locked_payment_is_idempotent(): void
    {
        $service = new FxLockService();
        $payment = new Payment();
        $payment->amount = 10000;
        $payment->currency = 'AED';
        $payment->fx_rate_snapshot = 3.6725;
        $payment->fx_base_currency = 'AED';
        $payment->fx_locked_at = now()->subDay();
        $payment->amount_in_base = 36725;

        $service->lock($payment);

        // Rate must NOT change on a re-lock.
        $this->assertSame(3.6725, (float) $payment->fx_rate_snapshot);
        $this->assertSame(36725.0, (float) $payment->amount_in_base);
    }

    public function test_payment_currency_is_uppercased_in_lookup(): void
    {
        $service = new FxLockService();
        $payment = new Payment();
        $payment->amount = 1000;
        $payment->currency = 'aed';

        $service->lock($payment);

        $this->assertSame(1.0, (float) $payment->fx_rate_snapshot);
    }
}
