<?php

namespace App\Console\Commands;

use App\Jobs\ScreenCompany;
use App\Models\Company;
use App\Models\SanctionsScreening;
use Illuminate\Console\Command;

/**
 * Daily re-screening of every active company against the sanctions
 * provider. Phase 2 / Sprint 7 / task 2.4.
 *
 * Sanctions lists are updated continuously — a company that was clean
 * yesterday can be added to OFAC today. Without a re-screen we'd miss
 * the new addition until the next manual review.
 *
 * Strategy:
 *   - Re-screen every active company that hasn't been screened in the
 *     last 7 days, OR was last screened with `error` (network failure
 *     last time, retry sooner).
 *   - Dispatch each company to the queue (one ScreenCompany job each)
 *     so the daily run never blocks the scheduler tick. The queue worker
 *     processes them at its own pace.
 *   - Cache is bypassed (`useCache: false`) so we always get fresh
 *     verdicts — the whole point is to catch list updates.
 *
 * Scheduled in routes/console.php to run daily at 04:00 UTC.
 */
class RescreenCompanies extends Command
{
    protected $signature = 'sanctions:rescreen
                            {--all : Re-screen every company regardless of last-screened date}
                            {--limit= : Maximum number of companies to enqueue}';

    protected $description = 'Re-run sanctions screening for every company that hasn\'t been screened recently.';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);
        $limit  = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Company::query()
            ->where('status', 'active')
            ->when(!$this->option('all'), function ($q) use ($cutoff) {
                $q->where(function ($qq) use ($cutoff) {
                    $qq->whereNull('sanctions_screened_at')
                        ->orWhere('sanctions_screened_at', '<', $cutoff)
                        ->orWhere('sanctions_status', SanctionsScreening::RESULT_ERROR);
                });
            })
            ->orderBy('sanctions_screened_at', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $count = 0;
        $query->chunkById(100, function ($companies) use (&$count) {
            foreach ($companies as $company) {
                ScreenCompany::dispatch(
                    companyId: $company->id,
                    triggeredBy: null, // System-initiated; not a user.
                    useCache: false,
                );
                $count++;
            }
        });

        $this->info("Dispatched {$count} ScreenCompany job(s).");

        return self::SUCCESS;
    }
}
