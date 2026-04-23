<?php

namespace Tests\Unit\Services\Payments;

use App\Models\Company;
use App\Models\Payment;
use App\Services\Payments\WhtService;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the Withholding Tax service. No DB access — the
 * service accepts a Payment + Contract and mutates in memory.
 */
class WhtServiceTest extends TestCase
{
    private WhtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhtService();
    }

    public function test_zero_rate_produces_zero_withholding(): void
    {
        $payment = $this->makePayment(amount: 10000, whtRate: 0);

        $this->service->apply($payment);

        $this->assertSame(0, (int) $payment->wht_rate);
        $this->assertSame(0, (int) $payment->wht_amount);
    }

    public function test_explicit_rate_is_applied_even_without_recipient(): void
    {
        $payment = $this->makePayment(amount: 10000, whtRate: 5);

        $this->service->apply($payment);

        $this->assertSame(5.0, (float) $payment->wht_rate);
        $this->assertSame(500.0, (float) $payment->wht_amount);
    }

    public function test_rate_is_capped_at_thirty_percent(): void
    {
        $payment = $this->makePayment(amount: 10000, whtRate: 50);

        $this->service->apply($payment);

        $this->assertSame(30.0, (float) $payment->wht_rate);
        $this->assertSame(3000.0, (float) $payment->wht_amount);
    }

    public function test_fractional_withholding_is_rounded_to_two_decimals(): void
    {
        $payment = $this->makePayment(amount: 1234.56, whtRate: 7.5);

        $this->service->apply($payment);

        $this->assertSame(92.59, round((float) $payment->wht_amount, 2));
    }

    private function makePayment(float $amount, ?float $whtRate): Payment
    {
        $p = new Payment();
        $p->amount = $amount;
        $p->wht_rate = $whtRate;
        $p->setRelation('contract', null);
        $p->recipient_company_id = null;

        return $p;
    }
}
