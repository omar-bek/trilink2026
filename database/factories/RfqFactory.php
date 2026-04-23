<?php

namespace Database\Factories;

use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Models\Company;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    protected $model = Rfq::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'company_id' => Company::factory(),
            'rfq_number' => sprintf('RFQ-%d-%05d', date('Y'), $counter),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'type' => RfqType::SUPPLIER->value,
            'status' => RfqStatus::OPEN->value,
            'budget' => fake()->numberBetween(10000, 500000),
            'currency' => 'AED',
            'deadline' => now()->addDays(14),
            'items' => [
                ['description' => 'Demo line item', 'quantity' => 1, 'unit' => 'pcs'],
            ],
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => RfqStatus::OPEN->value]);
    }

    public function closed(): self
    {
        return $this->state(['status' => RfqStatus::CLOSED->value]);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => RfqStatus::CANCELLED->value]);
    }
}
