<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 200000);

        return [
            'contract_id' => Contract::factory(),
            'company_id' => Company::factory()->buyer(),
            'recipient_company_id' => Company::factory()->supplier(),
            'buyer_id' => User::factory(),
            'status' => PaymentStatus::PENDING_APPROVAL->value,
            'amount' => $amount,
            'vat_rate' => 5.0,
            'vat_amount' => round($amount * 0.05, 2),
            'total_amount' => round($amount * 1.05, 2),
            'currency' => 'AED',
            'milestone' => 'delivery',
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => PaymentStatus::PENDING_APPROVAL->value]);
    }

    public function approved(): self
    {
        return $this->state([
            'status' => PaymentStatus::APPROVED->value,
            'approved_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => PaymentStatus::COMPLETED->value,
            'approved_at' => now()->subDays(3),
            'paid_date' => now()->subDays(1),
            'settled_at' => now()->subDays(1),
        ]);
    }

    public function fxLocked(string $currency = 'AED', float $rate = 1.0): self
    {
        return $this->state(fn ($attrs) => [
            'currency' => $currency,
            'fx_rate_snapshot' => $rate,
            'fx_base_currency' => 'AED',
            'fx_locked_at' => now(),
            'amount_in_base' => round(((float) $attrs['amount']) * $rate, 2),
        ]);
    }

    public function withDualApproval(): self
    {
        return $this->state([
            'amount' => 600000,
            'total_amount' => 630000,
            'requires_dual_approval' => true,
        ]);
    }

    public function forContract(Contract $contract): self
    {
        return $this->state([
            'contract_id' => $contract->id,
            'company_id' => $contract->buyer_company_id,
            'currency' => $contract->currency ?? 'AED',
        ]);
    }
}
