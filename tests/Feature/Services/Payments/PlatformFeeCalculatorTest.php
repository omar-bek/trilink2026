<?php

namespace Tests\Feature\Services\Payments;

use App\Models\Payment;
use App\Models\PlatformFeeAllocation;
use App\Services\Payments\PlatformFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocate_creates_transaction_and_escrow_rows(): void
    {
        config(['payments.fees.transaction' => 0.0125, 'payments.fees.escrow' => 0.005]);

        $calc = new PlatformFeeCalculator();
        $payment = Payment::factory()->create(['amount' => 100000, 'currency' => 'AED']);

        $calc->allocate($payment);

        $this->assertDatabaseHas('platform_fee_allocations', [
            'payment_id' => $payment->id,
            'fee_type' => PlatformFeeAllocation::TYPE_TRANSACTION,
            'fee_amount' => 1250,
        ]);
        $this->assertDatabaseHas('platform_fee_allocations', [
            'payment_id' => $payment->id,
            'fee_type' => PlatformFeeAllocation::TYPE_ESCROW,
            'fee_amount' => 500,
        ]);
    }

    public function test_allocate_is_idempotent(): void
    {
        config(['payments.fees.transaction' => 0.0125, 'payments.fees.escrow' => 0.005]);

        $calc = new PlatformFeeCalculator();
        $payment = Payment::factory()->create(['amount' => 50000, 'currency' => 'AED']);

        $calc->allocate($payment);
        $calc->allocate($payment);

        $this->assertSame(2, PlatformFeeAllocation::where('payment_id', $payment->id)->count());
    }

    public function test_zero_amount_payment_skips_allocation(): void
    {
        $calc = new PlatformFeeCalculator();
        $payment = Payment::factory()->create(['amount' => 0, 'currency' => 'AED']);

        $calc->allocate($payment);

        $this->assertSame(0, PlatformFeeAllocation::where('payment_id', $payment->id)->count());
    }
}
