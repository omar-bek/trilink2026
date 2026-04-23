<?php

namespace Tests\Unit;

use App\Enums\PaymentTerms;
use Tests\TestCase;

class PaymentTermsTest extends TestCase
{
    public function test_days_per_term(): void
    {
        $this->assertEquals(0, PaymentTerms::COD->days());
        $this->assertEquals(15, PaymentTerms::NET_15->days());
        $this->assertEquals(30, PaymentTerms::NET_30->days());
        $this->assertEquals(60, PaymentTerms::NET_60->days());
        $this->assertEquals(90, PaymentTerms::NET_90->days());
    }

    public function test_two_ten_net_thirty_exposes_discount(): void
    {
        $this->assertSame(2.0, PaymentTerms::TWO_TEN_NET_30->earlyDiscountRate());
        $this->assertSame(10, PaymentTerms::TWO_TEN_NET_30->earlyDiscountDays());
    }

    public function test_other_terms_have_no_discount(): void
    {
        $this->assertNull(PaymentTerms::NET_30->earlyDiscountRate());
    }

    public function test_eom_flag(): void
    {
        $this->assertTrue(PaymentTerms::EOM_30->isEndOfMonth());
        $this->assertFalse(PaymentTerms::NET_30->isEndOfMonth());
    }
}
