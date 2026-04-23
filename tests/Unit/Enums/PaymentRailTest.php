<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentRail;
use PHPUnit\Framework\TestCase;

class PaymentRailTest extends TestCase
{
    public function test_card_rails_are_flagged_correctly(): void
    {
        $this->assertTrue(PaymentRail::STRIPE->isCard());
        $this->assertTrue(PaymentRail::NETWORK_INTERNATIONAL->isCard());
        $this->assertFalse(PaymentRail::UAEFTS->isCard());
        $this->assertFalse(PaymentRail::CHEQUE->isCard());
    }

    public function test_instant_rails_are_flagged_correctly(): void
    {
        $this->assertTrue(PaymentRail::IPI->isInstant());
        $this->assertTrue(PaymentRail::AANI->isInstant());
        $this->assertTrue(PaymentRail::NOQODI->isInstant());
        $this->assertTrue(PaymentRail::EDIRHAM->isInstant());
        $this->assertFalse(PaymentRail::SWIFT_WIRE->isInstant());
    }

    public function test_paper_rails_are_flagged_correctly(): void
    {
        $this->assertTrue(PaymentRail::CHEQUE->isPaper());
        $this->assertTrue(PaymentRail::POSTDATED_CHEQUE->isPaper());
        $this->assertFalse(PaymentRail::UAEFTS->isPaper());
        $this->assertFalse(PaymentRail::WPS->isPaper());
    }

    public function test_settlement_window_for_each_rail(): void
    {
        $this->assertSame(0, PaymentRail::ESCROW->settlementWindowHours());
        $this->assertSame(1, PaymentRail::IPI->settlementWindowHours());
        $this->assertSame(4, PaymentRail::UAEFTS->settlementWindowHours());
        $this->assertSame(4, PaymentRail::WPS->settlementWindowHours());
        $this->assertSame(48, PaymentRail::CHEQUE->settlementWindowHours());
        $this->assertSame(72, PaymentRail::SWIFT_WIRE->settlementWindowHours());
        $this->assertSame(720, PaymentRail::POSTDATED_CHEQUE->settlementWindowHours());
    }
}
