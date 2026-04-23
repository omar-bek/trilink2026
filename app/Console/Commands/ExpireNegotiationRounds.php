<?php

namespace App\Console\Commands;

use App\Services\NegotiationService;
use Illuminate\Console\Command;

/**
 * Sweeps stale counter-offers. Any round whose `expires_at` is in the
 * past while still OPEN is flipped to REJECTED (auto-expired) with an
 * audit trail row and no notifications (the parties were already warned
 * at post time with the expiry timestamp).
 *
 * Runs every 5 minutes from the scheduler. Idempotent — a round already
 * closed by a human action is skipped because the query only touches
 * round_status = OPEN.
 */
class ExpireNegotiationRounds extends Command
{
    protected $signature = 'negotiation:expire-rounds';

    protected $description = 'Auto-reject counter-offers past their expires_at timestamp.';

    public function handle(NegotiationService $service): int
    {
        $count = $service->expireStaleRounds();

        $this->info(sprintf('Expired %d negotiation round(s).', $count));

        return self::SUCCESS;
    }
}
