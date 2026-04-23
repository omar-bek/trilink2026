<?php

namespace Database\Factories;

use App\Enums\ChequeStatus;
use App\Models\Company;
use App\Models\PostdatedCheque;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostdatedCheque>
 */
class PostdatedChequeFactory extends Factory
{
    protected $model = PostdatedCheque::class;

    public function definition(): array
    {
        return [
            'cheque_number' => 'CHQ-'.fake()->unique()->numerify('########'),
            'issuer_company_id' => Company::factory()->buyer(),
            'beneficiary_company_id' => Company::factory()->supplier(),
            'drawer_bank_name' => fake()->randomElement(['Emirates NBD', 'Mashreq Bank', 'Abu Dhabi Commercial Bank', 'First Abu Dhabi Bank']),
            'drawer_bank_swift' => 'EBILAEAD',
            'drawer_account_iban' => 'AE07033'.fake()->numerify('#################'),
            'issue_date' => now()->toDateString(),
            'presentation_date' => now()->addDays(30)->toDateString(),
            'amount' => fake()->numberBetween(5000, 100000),
            'currency' => 'AED',
            'status' => ChequeStatus::ISSUED->value,
            'created_by' => User::factory(),
        ];
    }

    public function issued(): self
    {
        return $this->state(['status' => ChequeStatus::ISSUED->value]);
    }

    public function deposited(): self
    {
        return $this->state([
            'status' => ChequeStatus::DEPOSITED->value,
            'presentation_date' => now()->subDays(1)->toDateString(),
            'deposited_at' => now(),
        ]);
    }

    public function cleared(): self
    {
        return $this->state([
            'status' => ChequeStatus::CLEARED->value,
            'presentation_date' => now()->subDays(3)->toDateString(),
            'deposited_at' => now()->subDays(2),
            'cleared_at' => now()->subDays(1),
        ]);
    }

    public function returned(string $reason = 'Insufficient funds'): self
    {
        return $this->state([
            'status' => ChequeStatus::RETURNED->value,
            'presentation_date' => now()->subDays(3)->toDateString(),
            'deposited_at' => now()->subDays(2),
            'returned_at' => now()->subDays(1),
            'return_reason' => $reason,
        ]);
    }
}
