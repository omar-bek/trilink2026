<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractRenewalAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Daily renewal alerts for contracts approaching their end date.
 * Buckets are 90 / 60 / 30 days from end_date — each contract is
 * notified at most ONCE per bucket per (contract × bucket) tuple,
 * tracked in cache for 200 days so the same alert never repeats
 * even if the cron is re-run on the same day.
 *
 * Only contracts in ACTIVE status are considered. SIGNED is a
 * transient stage that flips to ACTIVE the moment all parties have
 * countersigned (see ContractService::sign()), so a contract that
 * lingers in SIGNED for 30+ days is effectively unreachable. Including
 * SIGNED here was a bug — it risked double-firing renewal alerts when
 * the cache key was cleared between the SIGNED bucket and the ACTIVE
 * bucket. DRAFT, PENDING_SIGNATURES, COMPLETED, CANCELLED and
 * TERMINATED contracts are also skipped because there is nothing to
 * renew.
 */
class SendContractRenewalAlertsCommand extends Command
{
    protected $signature = 'contracts:renewal-alerts';

    protected $description = 'Notify contract parties when contracts are approaching their end date (90/60/30 days).';

    /**
     * Day-out buckets in descending order. We process the LARGEST
     * bucket first so a contract that is 30 days out doesn't get
     * the 60-day or 90-day reminder fired retroactively.
     */
    private const BUCKETS = [90, 60, 30];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $totalNotified = 0;

        foreach (self::BUCKETS as $daysOut) {
            // Match contracts whose end_date is EXACTLY $daysOut days
            // from today. Using a single-day window keeps the math
            // simple and means a contract that creeps from 91 → 90 →
            // 89 days only fires the 90-day alert once.
            $targetDate = $today->copy()->addDays($daysOut)->toDateString();

            $contracts = Contract::query()
                ->where('status', ContractStatus::ACTIVE->value)
                ->whereNotNull('end_date')
                ->whereDate('end_date', $targetDate)
                ->get();

            foreach ($contracts as $contract) {
                $cacheKey = "contract:renewal-alert:{$contract->id}:{$daysOut}";
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $partyCompanyIds = collect($contract->parties ?? [])
                    ->pluck('company_id')
                    ->push($contract->buyer_company_id)
                    ->filter()
                    ->unique()
                    ->all();
                if (empty($partyCompanyIds)) {
                    continue;
                }

                // Recipient filter — only managers + finance roles
                // get renewal alerts. Spamming every employee defeats
                // the purpose; the people who decide on renewal are
                // a known subset.
                $recipients = User::whereIn('company_id', $partyCompanyIds)
                    ->whereIn('role', ['company_manager', 'finance', 'buyer', 'admin'])
                    ->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new ContractRenewalAlertNotification($contract, $daysOut));
                    $totalNotified += $recipients->count();
                }

                // Mark this (contract, bucket) tuple as notified for
                // 200 days so a re-run cannot duplicate it.
                Cache::put($cacheKey, true, now()->addDays(200));
            }

            $this->info("Bucket {$daysOut}d: processed " . $contracts->count() . " contract(s).");
        }

        $this->info("Sent {$totalNotified} renewal notification(s).");
        return self::SUCCESS;
    }
}
