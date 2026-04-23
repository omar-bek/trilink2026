<?php

namespace Tests\Feature\Services\Payments;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\Payments\EarlyDiscountService;
use App\Services\PaymentTermsService;
use App\Services\SettlementCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyDiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    private EarlyDiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EarlyDiscountService(
            new PaymentTermsService(new SettlementCalendarService()),
        );
    }

    public function test_no_discount_without_terms(): void
    {
        $payment = Payment::factory()->create(['amount' => 10000]);
        $this->assertSame(0.0, $this->service->preview($payment));
    }

    public function test_applies_two_percent_inside_window(): void
    {
        $contract = Contract::factory()->create([
            'payment_terms' => '2_10_net_30',
            'early_discount_rate' => 2.0,
            'early_discount_days' => 10,
        ]);
        $payment = Payment::factory()->forContract($contract)->approved()->create([
            'amount' => 10000,
            'invoice_issued_at' => now()->subDays(3),
        ]);

        $applied = $this->service->applyOnSettlement($payment);

        $this->assertSame(200.0, $applied);
        $this->assertSame(200.0, (float) $payment->fresh()->early_discount_amount);
    }

    public function test_no_discount_past_cutoff(): void
    {
        $contract = Contract::factory()->create([
            'payment_terms' => '2_10_net_30',
            'early_discount_rate' => 2.0,
            'early_discount_days' => 10,
        ]);
        $payment = Payment::factory()->forContract($contract)->approved()->create([
            'amount' => 10000,
            'invoice_issued_at' => now()->subDays(15),
        ]);

        $this->assertSame(0.0, $this->service->applyOnSettlement($payment));
    }
}
