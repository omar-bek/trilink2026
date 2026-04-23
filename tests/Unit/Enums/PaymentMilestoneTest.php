<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentMilestone;
use PHPUnit\Framework\TestCase;

class PaymentMilestoneTest extends TestCase
{
    public function test_every_milestone_has_a_label(): void
    {
        foreach (PaymentMilestone::cases() as $m) {
            $this->assertNotEmpty($m->label());
        }
    }

    public function test_all_expected_values_present(): void
    {
        $values = array_map(fn ($c) => $c->value, PaymentMilestone::cases());

        foreach (['advance', 'production', 'delivery', 'inspection', 'final', 'retention', 'retention_release', 'late_fee', 'credit_note'] as $expected) {
            $this->assertContains($expected, $values);
        }
    }
}
