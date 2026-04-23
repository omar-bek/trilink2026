<?php

namespace Database\Factories;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(5000, 400000);

        return [
            'rfq_id' => Rfq::factory(),
            'company_id' => Company::factory()->supplier(),
            'provider_id' => User::factory(),
            'status' => BidStatus::SUBMITTED->value,
            'price' => $price,
            'currency' => 'AED',
            'delivery_time_days' => fake()->numberBetween(7, 45),
            'payment_terms' => '30% advance, 50% production, 20% delivery',
            'validity_date' => now()->addDays(30),
            'is_anonymous' => false,
            // VAT snapshot — exclusive @ 5% default.
            'tax_treatment' => 'exclusive',
            'tax_rate_snapshot' => 5.0,
            'subtotal_excl_tax' => $price,
            'tax_amount' => round($price * 0.05, 2),
            'total_incl_tax' => round($price * 1.05, 2),
        ];
    }

    public function submitted(): self
    {
        return $this->state(['status' => BidStatus::SUBMITTED->value]);
    }

    public function underReview(): self
    {
        return $this->state(['status' => BidStatus::UNDER_REVIEW->value]);
    }

    public function accepted(): self
    {
        return $this->state(['status' => BidStatus::ACCEPTED->value]);
    }

    public function rejected(): self
    {
        return $this->state(['status' => BidStatus::REJECTED->value]);
    }

    public function withdrawn(): self
    {
        return $this->state(['status' => BidStatus::WITHDRAWN->value]);
    }

    public function withRoundCap(int $cap): self
    {
        return $this->state(['negotiation_round_cap' => $cap]);
    }

    public function vatInclusive(): self
    {
        return $this->state(fn ($attrs) => [
            'tax_treatment' => 'inclusive',
            'subtotal_excl_tax' => round($attrs['price'] / 1.05, 2),
            'tax_amount' => round($attrs['price'] - ($attrs['price'] / 1.05), 2),
            'total_incl_tax' => $attrs['price'],
        ]);
    }
}
