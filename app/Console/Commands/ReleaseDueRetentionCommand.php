<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

/**
 * Phase G — daily sweep that creates a retention-release Payment for
 * every contract whose retention_release_date has passed and still has
 * an un-released balance.
 *
 * Schedule: daily at 04:00 GST via Kernel::schedule. The resulting
 * Payment lands in PENDING_APPROVAL so the buyer's finance user still
 * signs off before value moves.
 */
class ReleaseDueRetentionCommand extends Command
{
    protected $signature = 'retention:release-due
        {--dry-run : Show what would be released without creating payments}';

    protected $description = 'Create pending-approval retention-release payments for contracts past their retention_release_date.';

    public function handle(RetentionService $retention): int
    {
        $pending = $retention->pendingRelease();
        if ($pending->isEmpty()) {
            $this->info('No retention releases due today.');

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($pending as $contract) {
            $this->line("Contract {$contract->contract_number} — retention {$contract->currency} ".number_format((float) $contract->retention_amount, 2));
            if ($this->option('dry-run')) {
                continue;
            }

            try {
                $payment = $retention->releaseAll($contract);
                if ($payment) {
                    $created++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed for contract {$contract->id}: ".$e->getMessage());
            }
        }

        $this->info("Created {$created} retention-release payment(s).");

        return self::SUCCESS;
    }
}
