<?php

namespace Tests\Unit\Services;

use App\Models\Bid;
use App\Services\NegotiationVatCalculator;
use PHPUnit\Framework\TestCase;

class NegotiationVatCalculatorTest extends TestCase
{
    private NegotiationVatCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new NegotiationVatCalculator();
    }

    public function test_exclusive_treatment_adds_five_percent(): void
    {
        $bid = $this->bid('exclusive', 5.0);

        $out = $this->calc->recalculate($bid, 10000);

        $this->assertSame(10000.0, $out['subtotal_excl_tax']);
        $this->assertSame(500.0, $out['tax_amount']);
        $this->assertSame(10500.0, $out['total_incl_tax']);
        $this->assertSame('exclusive', $out['treatment']);
    }

    public function test_inclusive_treatment_extracts_tax_from_gross(): void
    {
        $bid = $this->bid('inclusive', 5.0);

        $out = $this->calc->recalculate($bid, 10500);

        $this->assertSame(10000.0, $out['subtotal_excl_tax']);
        $this->assertSame(500.0, $out['tax_amount']);
        $this->assertSame(10500.0, $out['total_incl_tax']);
    }

    public function test_not_applicable_treatment_passes_amount_through(): void
    {
        $bid = $this->bid('not_applicable', 5.0);

        $out = $this->calc->recalculate($bid, 10000);

        $this->assertSame(10000.0, $out['subtotal_excl_tax']);
        $this->assertSame(0.0, $out['tax_amount']);
        $this->assertSame(10000.0, $out['total_incl_tax']);
        $this->assertSame(0.0, $out['rate']);
    }

    public function test_negative_amount_is_clamped_to_zero(): void
    {
        $bid = $this->bid('exclusive', 5.0);

        $out = $this->calc->recalculate($bid, -100);

        $this->assertSame(0.0, $out['subtotal_excl_tax']);
        $this->assertSame(0.0, $out['total_incl_tax']);
    }

    public function test_bid_with_null_treatment_defaults_to_exclusive(): void
    {
        $bid = new Bid();
        $bid->tax_treatment = null;
        $bid->tax_rate_snapshot = null;

        $out = $this->calc->recalculate($bid, 10000);

        $this->assertSame('exclusive', $out['treatment']);
        $this->assertSame(5.0, $out['rate']); // UAE 5% default
    }

    private function bid(string $treatment, float $rate): Bid
    {
        $bid = new Bid();
        $bid->tax_treatment = $treatment;
        $bid->tax_rate_snapshot = $rate;

        return $bid;
    }
}
