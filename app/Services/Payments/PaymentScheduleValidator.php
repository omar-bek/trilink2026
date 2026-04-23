<?php

namespace App\Services\Payments;

use App\Enums\PaymentMilestone;
use RuntimeException;

/**
 * Validates a contract's payment_schedule JSON before it's saved. Catches
 * the common defects that silently broke downstream math:
 *
 *   - milestone percentages must sum to 100% (±0.01 tolerance for
 *     rounding); otherwise money quietly leaks from the contract total.
 *   - milestone keys must belong to PaymentMilestone enum; free text was
 *     too fragile (typos broke auto-release + reconciliation).
 *   - retention percentage must be 0-20% (UAE B2B norm — anything above
 *     is flagged as commercially abusive).
 *   - at most one RETENTION milestone per schedule.
 *   - no two milestones share the same key except RETENTION + RETENTION_RELEASE.
 *   - due_date sequence must be non-decreasing (no "delivery before advance").
 *
 * Throws RuntimeException on any violation so the controller can surface
 * the exact problem as a flash error.
 */
class PaymentScheduleValidator
{
    public const MAX_RETENTION_PCT = 20.0;

    /**
     * @param  array<int, array<string, mixed>>  $schedule
     */
    public function validate(array $schedule, float $contractTotal): void
    {
        if (empty($schedule)) {
            return;
        }

        $this->assertAllMilestonesKnown($schedule);
        $this->assertUniqueMilestones($schedule);
        $this->assertPercentagesSumToOneHundred($schedule);
        $this->assertRetentionWithinCap($schedule);
        $this->assertDueDateOrder($schedule);
        $this->assertAmountsMatchTotal($schedule, $contractTotal);
    }

    private function assertAllMilestonesKnown(array $schedule): void
    {
        $valid = array_map(fn ($c) => $c->value, PaymentMilestone::cases());
        foreach ($schedule as $i => $row) {
            $key = (string) ($row['milestone'] ?? '');
            if ($key === '' || ! in_array($key, $valid, true)) {
                throw new RuntimeException(sprintf(
                    'Payment schedule row %d has unknown milestone "%s". Allowed: %s',
                    $i + 1,
                    $key,
                    implode(', ', $valid),
                ));
            }
        }
    }

    private function assertUniqueMilestones(array $schedule): void
    {
        $seen = [];
        foreach ($schedule as $i => $row) {
            $key = (string) ($row['milestone'] ?? '');
            if (isset($seen[$key])) {
                throw new RuntimeException(sprintf(
                    'Payment schedule row %d duplicates milestone "%s" (first seen at row %d).',
                    $i + 1,
                    $key,
                    $seen[$key] + 1,
                ));
            }
            $seen[$key] = $i;
        }
    }

    private function assertPercentagesSumToOneHundred(array $schedule): void
    {
        $sum = 0.0;
        foreach ($schedule as $row) {
            if (($row['is_retention'] ?? false) && ($row['milestone'] ?? null) === PaymentMilestone::RETENTION_RELEASE->value) {
                // Release row is a return of retained, not a new milestone.
                continue;
            }
            $sum += (float) ($row['percentage'] ?? 0);
        }

        if (abs($sum - 100.0) > 0.01) {
            throw new RuntimeException(sprintf(
                'Payment schedule percentages must sum to 100%% (got %.2f%%).',
                $sum,
            ));
        }
    }

    private function assertRetentionWithinCap(array $schedule): void
    {
        $retentionRow = null;
        foreach ($schedule as $row) {
            if (($row['milestone'] ?? null) === PaymentMilestone::RETENTION->value) {
                $retentionRow = $row;
                break;
            }
        }

        if (! $retentionRow) {
            return;
        }

        $pct = (float) ($retentionRow['percentage'] ?? 0);
        if ($pct < 0 || $pct > self::MAX_RETENTION_PCT) {
            throw new RuntimeException(sprintf(
                'Retention percentage %.2f%% is outside the allowed range 0-%.2f%% (UAE B2B norm).',
                $pct,
                self::MAX_RETENTION_PCT,
            ));
        }
    }

    private function assertDueDateOrder(array $schedule): void
    {
        $prev = null;
        foreach ($schedule as $i => $row) {
            $due = $row['due_date'] ?? null;
            if (! $due) {
                continue;
            }
            $ts = strtotime((string) $due);
            if ($ts === false) {
                throw new RuntimeException(sprintf(
                    'Payment schedule row %d has unparseable due_date "%s".',
                    $i + 1,
                    $due,
                ));
            }
            if ($prev !== null && $ts < $prev) {
                throw new RuntimeException(sprintf(
                    'Payment schedule row %d has due_date before the preceding milestone.',
                    $i + 1,
                ));
            }
            $prev = $ts;
        }
    }

    private function assertAmountsMatchTotal(array $schedule, float $contractTotal): void
    {
        if ($contractTotal <= 0) {
            return;
        }
        $sum = 0.0;
        foreach ($schedule as $row) {
            if (($row['milestone'] ?? null) === PaymentMilestone::RETENTION_RELEASE->value) {
                continue;
            }
            $sum += (float) ($row['amount'] ?? 0);
        }

        // Allow fils-level drift across N milestones.
        $tolerance = max(0.05, $contractTotal * 0.0001);
        if (abs($sum - $contractTotal) > $tolerance) {
            throw new RuntimeException(sprintf(
                'Payment schedule amounts sum to %.2f but contract total is %.2f (diff %.2f).',
                $sum,
                $contractTotal,
                abs($sum - $contractTotal),
            ));
        }
    }
}
