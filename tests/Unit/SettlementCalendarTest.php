<?php

namespace Tests\Unit;

use App\Services\SettlementCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_saturday_is_not_a_business_day(): void
    {
        $cal = app(SettlementCalendarService::class);
        // 2026-04-25 is a Saturday.
        $this->assertFalse($cal->isBusinessDay(Carbon::parse('2026-04-25')));
    }

    public function test_sunday_is_not_a_business_day_under_new_uae_weekend(): void
    {
        $cal = app(SettlementCalendarService::class);
        // 2026-04-26 is a Sunday.
        $this->assertFalse($cal->isBusinessDay(Carbon::parse('2026-04-26')));
    }

    public function test_monday_is_a_business_day(): void
    {
        $cal = app(SettlementCalendarService::class);
        $this->assertTrue($cal->isBusinessDay(Carbon::parse('2026-04-27')));
    }

    public function test_next_business_day_skips_weekend(): void
    {
        $cal = app(SettlementCalendarService::class);
        $result = $cal->nextBusinessDay(Carbon::parse('2026-04-25')); // Sat
        $this->assertEquals('2026-04-27', $result->toDateString()); // Mon
    }

    public function test_next_business_day_skips_national_day(): void
    {
        $cal = app(SettlementCalendarService::class);
        // 2026-12-02 is National Day (seeded holiday).
        $result = $cal->nextBusinessDay(Carbon::parse('2026-12-02'));
        $this->assertNotEquals('2026-12-02', $result->toDateString());
    }

    public function test_add_business_days_honors_weekends_and_holidays(): void
    {
        $cal = app(SettlementCalendarService::class);
        // Starting Mon 2026-04-27, count 5 business days forward:
        //   +1 Tue 28, +2 Wed 29, +3 Thu 30, +4 Fri 1 May,
        //   (skip Sat 2 + Sun 3), +5 Mon 4 May.
        $result = $cal->addBusinessDays(Carbon::parse('2026-04-27'), 5);
        $this->assertEquals('2026-05-04', $result->toDateString());
    }
}
