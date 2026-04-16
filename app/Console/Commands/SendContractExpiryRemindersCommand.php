<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Daily — fires the "this contract is winding down" reminder for the
 * 30 / 7 / 1 day buckets before end_date. Different from
 * SendContractRenewalAlertsCommand which targets the 90/60/30 buckets
 * for ACTIVE contracts that ARE going to renew. This one is the
 * countdown for contracts that will simply end.
 */
class SendContractExpiryRemindersCommand extends Command
{
    protected $signature = 'contracts:expiry-reminders';

    protected $description = 'Notify both parties when an active contract is approaching its end date (30/7/1 days).';

    private const BUCKETS = [30, 7, 1];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $totalNotified = 0;

        foreach (self::BUCKETS as $daysOut) {
            $targetDate = $today->copy()->addDays($daysOut)->toDateString();

            $contracts = Contract::query()
                ->where('status', ContractStatus::ACTIVE->value)
                ->whereNotNull('end_date')
                ->whereDate('end_date', $targetDate)
                ->get();

            foreach ($contracts as $contract) {
                $cacheKey = "contract:expiring:{$contract->id}:{$daysOut}";
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

                $recipients = User::whereIn('company_id', $partyCompanyIds)
                    ->whereIn('role', ['company_manager', 'finance', 'buyer', 'admin'])
                    ->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new ContractExpiringNotification($contract, $daysOut));
                    $totalNotified += $recipients->count();
                }

                Cache::put($cacheKey, true, now()->addDays(60));
            }

            $this->info("Bucket {$daysOut}d: processed ".$contracts->count().' contract(s).');
        }

        $this->info("Sent {$totalNotified} expiry reminder(s).");

        return self::SUCCESS;
    }
}
