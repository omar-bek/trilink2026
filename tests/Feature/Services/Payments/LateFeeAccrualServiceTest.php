<?php

namespace Tests\Feature\Services\Payments;

use App\Enums\PaymentMilestone;
use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Services\Payments\LateFeeAccrualService;
use App\Services\PaymentTermsService;
use App\Services\SettlementCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateFeeAccrualServiceTest extends TestCase
{
    use RefreshDatabase;

    private LateFeeAccrualService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LateFeeAccrualService(
            new PaymentTermsService(new SettlementCalendarService()),
        );
    }

    public function test_no_accrual_when_not_overdue(): void
    {
        $payment = Payment::factory()->approved()->create([
            'due_date' => now()->addDays(10),
        ]);

        $this->assertNull($this->service->accrueFor($payment));
    }

    public function test_no_accrual_without_contract_rate(): void
    {
        $contract = Contract::factory()->create(['late_fee_annual_rate' => 0]);
        $payment = Payment::factory()->forContract($contract)->approved()->create([
            'due_date' => now()->subDays(30),
        ]);

        $this->assertNull($this->service->accrueFor($payment));
    }

    public function test_accrual_creates_late_fee_payment_row(): void
    {
        $contract = Contract::factory()->create(['late_fee_annual_rate' => 12]);
        $payment = Payment::factory()->forContract($contract)->approved()->create([
            'amount' => 10000,
            'due_date' => now()->subDays(30),
        ]);

        $result = $this->service->accrueFor($payment);

        $this->assertNotNull($result);
        $this->assertSame(PaymentMilestone::LATE_FEE->value, $result->milestone);
        $this->assertTrue((bool) $result->is_late_fee_accrual);
        $this->assertSame($payment->id, (int) $result->parent_payment_id);
        $this->assertSame(PaymentStatus::PENDING_APPROVAL->value, $result->status->value);
    }

    public function test_idempotent_per_month(): void
    {
        $contract = Contract::factory()->create(['late_fee_annual_rate' => 12]);
        $payment = Payment::factory()->forContract($contract)->approved()->create([
            'amount' => 10000,
            'due_date' => now()->subDays(30),
        ]);

        $first = $this->service->accrueFor($payment);
        $second = $this->service->accrueFor($payment);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
    }

    public function test_accrual_row_is_not_itself_re_accrued(): void
    {
        $contract = Contract::factory()->create(['late_fee_annual_rate' => 12]);
        $accrual = Payment::factory()->forContract($contract)->create([
            'is_late_fee_accrual' => true,
            'due_date' => now()->subDays(30),
        ]);

        $this->assertNull($this->service->accrueFor($accrual));
    }
}
