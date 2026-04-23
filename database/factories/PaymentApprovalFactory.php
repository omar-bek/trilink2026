<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentApproval>
 */
class PaymentApprovalFactory extends Factory
{
    protected $model = PaymentApproval::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'approver_id' => User::factory(),
            'role' => PaymentApproval::ROLE_PRIMARY,
            'action' => PaymentApproval::ACTION_APPROVED,
            'amount_snapshot' => 500000,
            'currency_snapshot' => 'AED',
            'ip_address' => '94.200.10.42',
            'user_agent' => 'Factory/Test',
        ];
    }

    public function primary(): self
    {
        return $this->state(['role' => PaymentApproval::ROLE_PRIMARY]);
    }

    public function secondary(): self
    {
        return $this->state(['role' => PaymentApproval::ROLE_SECONDARY]);
    }

    public function rejected(string $notes = 'Amount over limit'): self
    {
        return $this->state([
            'action' => PaymentApproval::ACTION_REJECTED,
            'notes' => $notes,
        ]);
    }
}
