<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NegotiationMessage>
 */
class NegotiationMessageFactory extends Factory
{
    protected $model = NegotiationMessage::class;

    public function definition(): array
    {
        return [
            'bid_id' => Bid::factory(),
            'sender_id' => User::factory(),
            'sender_side' => 'buyer',
            'kind' => NegotiationMessage::KIND_TEXT,
            'body' => fake()->sentence(),
            'round_status' => NegotiationMessage::ROUND_OPEN,
        ];
    }

    public function text(): self
    {
        return $this->state(['kind' => NegotiationMessage::KIND_TEXT]);
    }

    public function counterOffer(float $amount = 10000, int $round = 1): self
    {
        return $this->state([
            'kind' => NegotiationMessage::KIND_COUNTER_OFFER,
            'round_number' => $round,
            'body' => 'Counter offer round '.$round,
            'offer' => [
                'amount' => $amount,
                'currency' => 'AED',
                'delivery_days' => 10,
                'payment_terms' => '30% advance, 70% delivery',
                'tax_treatment' => 'exclusive',
                'tax_rate' => 5.0,
                'subtotal_excl_tax' => $amount,
                'tax_amount' => round($amount * 0.05, 2),
                'total_incl_tax' => round($amount * 1.05, 2),
            ],
            'subtotal_excl_tax' => $amount,
            'tax_amount' => round($amount * 0.05, 2),
            'total_incl_tax' => round($amount * 1.05, 2),
        ]);
    }

    public function open(): self
    {
        return $this->state([
            'round_status' => NegotiationMessage::ROUND_OPEN,
            'expires_at' => now()->addDays(2)->endOfDay(),
        ]);
    }

    public function accepted(string $signerName = 'Omar Al Mansouri'): self
    {
        return $this->state([
            'round_status' => NegotiationMessage::ROUND_ACCEPTED,
            'signed_by_name' => $signerName,
            'signed_at' => now(),
            'signature_ip' => '94.200.10.42',
            'signature_hash' => hash('sha256', 'demo|'.uniqid()),
            'responded_at' => now(),
        ]);
    }

    public function expired(): self
    {
        return $this->state([
            'round_status' => NegotiationMessage::ROUND_OPEN,
            'expires_at' => now()->subDays(1),
        ]);
    }

    public function fromSupplier(): self
    {
        return $this->state(['sender_side' => 'supplier']);
    }
}
