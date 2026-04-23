<?php

namespace Tests\Feature\Services;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Models\User;
use App\Services\NegotiationService;
use App\Services\NegotiationVatCalculator;
use App\Services\SettlementCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NegotiationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NegotiationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NegotiationService(
            new NegotiationVatCalculator(),
            new SettlementCalendarService(),
        );
    }

    public function test_opening_counter_offer_creates_round_with_vat_snapshot(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000, 'currency' => 'AED']);
        $sender = User::factory()->create(['company_id' => $bid->rfq->company_id]);

        $msg = $this->service->openCounterOffer($bid, $sender, [
            'amount' => 9000,
            'currency' => 'AED',
            'delivery_days' => 10,
            'payment_terms' => '30/70',
        ]);

        $this->assertSame(1, $msg->round_number);
        $this->assertSame('buyer', $msg->sender_side);
        $this->assertSame(9000.0, (float) $msg->offer['subtotal_excl_tax']);
        $this->assertSame(450.0, (float) $msg->offer['tax_amount']);
        $this->assertSame(9450.0, (float) $msg->offer['total_incl_tax']);
        $this->assertNotNull($msg->expires_at);
    }

    public function test_round_cap_blocks_further_counters(): void
    {
        $bid = Bid::factory()->withRoundCap(2)->underReview()->create(['price' => 10000]);
        $sender = User::factory()->create(['company_id' => $bid->rfq->company_id]);

        $this->service->openCounterOffer($bid, $sender, ['amount' => 9500]);
        $this->service->openCounterOffer($bid, $sender, ['amount' => 9000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/round cap/i');
        $this->service->openCounterOffer($bid, $sender, ['amount' => 8500]);
    }

    public function test_currency_is_locked_after_first_round(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000, 'currency' => 'AED']);
        $sender = User::factory()->create(['company_id' => $bid->rfq->company_id]);

        $this->service->openCounterOffer($bid, $sender, ['amount' => 9000, 'currency' => 'AED']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/currency/i');
        $this->service->openCounterOffer($bid, $sender, ['amount' => 2500, 'currency' => 'USD']);
    }

    public function test_accept_requires_signature_name(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000]);
        $sender = User::factory()->create(['company_id' => $bid->rfq->company_id]);
        $this->service->openCounterOffer($bid, $sender, ['amount' => 9000]);

        $this->expectException(RuntimeException::class);
        $this->service->acceptOffer($bid, $sender, ['name' => '']);
    }

    public function test_accept_stores_signature_hash_and_flips_bid_to_under_review(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000, 'currency' => 'AED']);
        $supplier = User::factory()->create(['company_id' => $bid->company_id]);
        $buyer = User::factory()->create(['company_id' => $bid->rfq->company_id]);

        $this->service->openCounterOffer($bid, $supplier, ['amount' => 9500]);
        $msg = $this->service->acceptOffer($bid->fresh(), $buyer, [
            'name' => 'Omar Al Mansouri',
            'ip' => '94.200.10.42',
        ]);

        $this->assertNotNull($msg);
        $this->assertSame(NegotiationMessage::ROUND_ACCEPTED, $msg->round_status);
        $this->assertSame('Omar Al Mansouri', $msg->signed_by_name);
        $this->assertNotEmpty($msg->signature_hash);
        $this->assertSame(BidStatus::UNDER_REVIEW->value, $bid->fresh()->status->value);
        $this->assertSame(9500.0, (float) $bid->fresh()->price);
    }

    public function test_expired_round_cannot_be_accepted(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000]);
        $supplier = User::factory()->create(['company_id' => $bid->company_id]);
        $buyer = User::factory()->create(['company_id' => $bid->rfq->company_id]);

        $this->service->openCounterOffer($bid, $supplier, ['amount' => 9500]);
        NegotiationMessage::query()
            ->where('bid_id', $bid->id)
            ->update(['expires_at' => now()->subDay()]);

        $result = $this->service->acceptOffer($bid->fresh(), $buyer, ['name' => 'Buyer Name']);
        $this->assertNull($result);
    }

    public function test_expire_stale_rounds_auto_rejects_past_due(): void
    {
        $bid = Bid::factory()->underReview()->create(['price' => 10000]);
        $supplier = User::factory()->create(['company_id' => $bid->company_id]);
        $this->service->openCounterOffer($bid, $supplier, ['amount' => 9500]);

        NegotiationMessage::query()->update(['expires_at' => now()->subHour()]);

        $count = $this->service->expireStaleRounds();

        $this->assertSame(1, $count);
        $this->assertSame(
            NegotiationMessage::ROUND_REJECTED,
            NegotiationMessage::query()->where('bid_id', $bid->id)->first()->round_status,
        );
    }
}
