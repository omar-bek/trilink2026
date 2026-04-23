<?php

namespace Tests\Unit\Enums;

use App\Enums\ChequeStatus;
use PHPUnit\Framework\TestCase;

class ChequeStatusTest extends TestCase
{
    public function test_issued_and_deposited_are_open(): void
    {
        $this->assertTrue(ChequeStatus::ISSUED->isOpen());
        $this->assertTrue(ChequeStatus::DEPOSITED->isOpen());

        $this->assertFalse(ChequeStatus::CLEARED->isOpen());
        $this->assertFalse(ChequeStatus::RETURNED->isOpen());
        $this->assertFalse(ChequeStatus::STOPPED->isOpen());
    }

    public function test_returned_and_stopped_are_dishonoured(): void
    {
        $this->assertTrue(ChequeStatus::RETURNED->isDishonoured());
        $this->assertTrue(ChequeStatus::STOPPED->isDishonoured());

        $this->assertFalse(ChequeStatus::CLEARED->isDishonoured());
        $this->assertFalse(ChequeStatus::ISSUED->isDishonoured());
    }

    public function test_each_status_has_a_human_label(): void
    {
        foreach (ChequeStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }
    }
}
