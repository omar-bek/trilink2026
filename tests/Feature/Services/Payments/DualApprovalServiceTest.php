<?php

namespace Tests\Feature\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentApproval;
use App\Models\User;
use App\Services\Payments\DualApprovalService;
use App\Services\Payments\FxLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DualApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private DualApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DualApprovalService(new FxLockService());
    }

    public function test_large_payment_triggers_dual_approval(): void
    {
        $contract = Contract::factory()->withDualApproval(500000)->create();
        $payment = Payment::factory()->forContract($contract)->create([
            'amount' => 600000,
            'currency' => 'AED',
        ]);

        $this->assertTrue($this->service->requiresDualApproval($payment));
    }

    public function test_small_payment_skips_dual_approval(): void
    {
        $contract = Contract::factory()->withDualApproval(500000)->create();
        $payment = Payment::factory()->forContract($contract)->create([
            'amount' => 50000,
            'currency' => 'AED',
        ]);

        $this->assertFalse($this->service->requiresDualApproval($payment));
    }

    public function test_primary_then_secondary_records_both_rows(): void
    {
        $contract = Contract::factory()->withDualApproval(500000)->create();
        $payment = Payment::factory()
            ->forContract($contract)
            ->create(['amount' => 600000, 'status' => PaymentStatus::PENDING_APPROVAL->value]);

        [$primary, $secondary] = User::factory()->count(2)->create();

        $this->service->recordPrimary($payment->fresh(), $primary);
        $this->service->recordSecondary($payment->fresh(), $secondary);

        $this->assertDatabaseHas('payment_approvals', [
            'payment_id' => $payment->id,
            'approver_id' => $primary->id,
            'role' => PaymentApproval::ROLE_PRIMARY,
        ]);
        $this->assertDatabaseHas('payment_approvals', [
            'payment_id' => $payment->id,
            'approver_id' => $secondary->id,
            'role' => PaymentApproval::ROLE_SECONDARY,
        ]);

        $payment->refresh();
        $this->assertSame(PaymentStatus::APPROVED->value, $payment->status->value);
        $this->assertSame($secondary->id, (int) $payment->second_approver_id);
    }

    public function test_same_user_cannot_sign_twice(): void
    {
        $payment = Payment::factory()->create([
            'amount' => 600000,
            'requires_dual_approval' => true,
        ]);
        $user = User::factory()->create();
        $this->service->recordPrimary($payment, $user);

        $this->expectException(RuntimeException::class);
        $this->service->recordSecondary($payment->fresh(), $user);
    }

    public function test_secondary_without_primary_throws(): void
    {
        $payment = Payment::factory()->create([
            'amount' => 600000,
            'requires_dual_approval' => true,
        ]);
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service->recordSecondary($payment, $user);
    }
}
