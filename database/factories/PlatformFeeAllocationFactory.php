<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PlatformFeeAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformFeeAllocation>
 */
class PlatformFeeAllocationFactory extends Factory
{
    protected $model = PlatformFeeAllocation::class;

    public function definition(): array
    {
        $base = fake()->numberBetween(10000, 200000);
        $rate = 0.0125;

        return [
            'payment_id' => Payment::factory(),
            'fee_type' => PlatformFeeAllocation::TYPE_TRANSACTION,
            'base_amount' => $base,
            'rate' => $rate,
            'fee_amount' => round($base * $rate, 2),
            'currency' => 'AED',
        ];
    }

    public function transaction(float $rate = 0.0125): self
    {
        return $this->state(fn ($attrs) => [
            'fee_type' => PlatformFeeAllocation::TYPE_TRANSACTION,
            'rate' => $rate,
            'fee_amount' => round(((float) $attrs['base_amount']) * $rate, 2),
        ]);
    }

    public function escrow(float $rate = 0.005): self
    {
        return $this->state(fn ($attrs) => [
            'fee_type' => PlatformFeeAllocation::TYPE_ESCROW,
            'rate' => $rate,
            'fee_amount' => round(((float) $attrs['base_amount']) * $rate, 2),
        ]);
    }
}
