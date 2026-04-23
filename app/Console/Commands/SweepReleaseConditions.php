<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Payments\ReleaseConditionEngine;
use Illuminate\Console\Command;

/**
 * Nightly sweeper that fires any payment whose release_condition has
 * matured but whose listener (ContractSigned / ShipmentDelivered / ...)
 * either dropped the event or didn't exist yet.
 *
 * Covers:
 *   - retention_period_elapsed — the retention_release_date is past.
 *   - manual rails where finance already flagged a payment "ready".
 *
 * Idempotent by construction: ReleaseConditionEngine skips payments that
 * are already COMPLETED or that have an escrow_release_id set.
 */
class SweepReleaseConditions extends Command
{
    protected $signature = 'escrow:sweep-release-conditions';

    protected $description = 'Auto-release escrow milestones whose release_condition has matured.';

    public function handle(ReleaseConditionEngine $engine): int
    {
        $released = 0;

        Contract::query()
            ->whereHas('escrowAccount')
            ->whereNotNull('retention_release_date')
            ->whereNull('retention_released_at')
            ->where('retention_release_date', '<=', now())
            ->cursor()
            ->each(function (Contract $c) use ($engine, &$released) {
                $released += $engine->onEvent($c, 'retention_period_elapsed');
            });

        $this->info(sprintf('Released %d payment(s) from matured escrow conditions.', $released));

        return self::SUCCESS;
    }
}
