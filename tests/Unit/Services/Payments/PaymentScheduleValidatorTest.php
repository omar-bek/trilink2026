<?php

namespace Tests\Unit\Services\Payments;

use App\Services\Payments\PaymentScheduleValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PaymentScheduleValidatorTest extends TestCase
{
    private PaymentScheduleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PaymentScheduleValidator();
    }

    public function test_empty_schedule_is_valid(): void
    {
        $this->validator->validate([], 0);
        $this->addToAssertionCount(1);
    }

    public function test_valid_three_milestone_schedule_passes(): void
    {
        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 30, 'amount' => 3000, 'due_date' => '2026-05-01'],
            ['milestone' => 'delivery', 'percentage' => 60, 'amount' => 6000, 'due_date' => '2026-06-01'],
            ['milestone' => 'retention', 'percentage' => 10, 'amount' => 1000, 'due_date' => '2026-07-01'],
        ], 10000);

        $this->addToAssertionCount(1);
    }

    public function test_percentages_below_hundred_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sum to 100/');

        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 30, 'amount' => 3000],
            ['milestone' => 'delivery', 'percentage' => 50, 'amount' => 5000],
        ], 8000);
    }

    public function test_retention_over_twenty_percent_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Retention percentage/');

        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 70, 'amount' => 7000],
            ['milestone' => 'retention', 'percentage' => 30, 'amount' => 3000],
        ], 10000);
    }

    public function test_unknown_milestone_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown milestone/');

        $this->validator->validate([
            ['milestone' => 'mystery', 'percentage' => 100, 'amount' => 10000],
        ], 10000);
    }

    public function test_duplicate_milestone_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/duplicates milestone/');

        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 30, 'amount' => 3000],
            ['milestone' => 'advance', 'percentage' => 70, 'amount' => 7000],
        ], 10000);
    }

    public function test_due_date_out_of_order_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/due_date before/');

        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 30, 'amount' => 3000, 'due_date' => '2026-06-01'],
            ['milestone' => 'delivery', 'percentage' => 70, 'amount' => 7000, 'due_date' => '2026-05-01'],
        ], 10000);
    }

    public function test_amounts_diverging_from_contract_total_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sum to .* but contract total/');

        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 30, 'amount' => 3000],
            ['milestone' => 'delivery', 'percentage' => 70, 'amount' => 5000],
        ], 10000);
    }

    public function test_fils_level_rounding_drift_tolerated(): void
    {
        // 10,000 × (33.33 + 33.33 + 33.34) split into fractional amounts.
        $this->validator->validate([
            ['milestone' => 'advance', 'percentage' => 33.33, 'amount' => 3333.33],
            ['milestone' => 'delivery', 'percentage' => 33.33, 'amount' => 3333.33],
            ['milestone' => 'final', 'percentage' => 33.34, 'amount' => 3333.34],
        ], 10000);

        $this->addToAssertionCount(1);
    }
}
