<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        $total = fake()->numberBetween(50000, 500000);

        return [
            'contract_number' => sprintf('CNT-%d-%04d', date('Y'), $counter),
            'title' => fake()->sentence(4),
            'status' => ContractStatus::ACTIVE->value,
            'buyer_company_id' => Company::factory()->buyer(),
            'total_amount' => $total,
            'currency' => 'AED',
            'start_date' => now()->subDays(7),
            'end_date' => now()->addDays(60),
            'payment_terms' => 'net_30',
            'parties' => [],
            'amounts' => [
                'subtotal' => round($total / 1.05, 2),
                'tax' => round($total - ($total / 1.05), 2),
                'total' => $total,
            ],
            'payment_schedule' => [
                ['milestone' => 'advance', 'percentage' => 30, 'amount' => round($total * 0.3, 2), 'due_date' => now()->addDays(7)->toDateString(), 'release_condition' => 'on_signature'],
                ['milestone' => 'delivery', 'percentage' => 60, 'amount' => round($total * 0.6, 2), 'due_date' => now()->addDays(30)->toDateString(), 'release_condition' => 'on_delivery'],
                ['milestone' => 'final', 'percentage' => 10, 'amount' => round($total * 0.1, 2), 'due_date' => now()->addDays(60)->toDateString(), 'release_condition' => 'manual'],
            ],
            'signatures' => [],
            'terms' => json_encode(['preamble' => 'Demo terms'], JSON_UNESCAPED_UNICODE),
        ];
    }

    public function active(): self
    {
        return $this->state(['status' => ContractStatus::ACTIVE->value]);
    }

    public function signed(): self
    {
        return $this->state(['status' => ContractStatus::SIGNED->value]);
    }

    public function completed(): self
    {
        return $this->state(['status' => ContractStatus::COMPLETED->value]);
    }

    public function withRetention(float $percentage = 10.0): self
    {
        return $this->state([
            'retention_percentage' => $percentage,
            'retention_release_date' => now()->addMonths(12),
        ]);
    }

    public function withDualApproval(float $threshold = 500000): self
    {
        return $this->state(['dual_approval_threshold_aed' => $threshold]);
    }
}
