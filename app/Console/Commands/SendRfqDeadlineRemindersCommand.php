<?php

namespace App\Console\Commands;

use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\RfqDeadlineReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Hourly cron — finds OPEN RFQs whose deadline is approaching and
 * pings every supplier in matching categories that hasn't bid yet.
 *
 * Buckets are 48 / 24 / 2 hours from deadline. The same supplier ×
 * RFQ × bucket tuple can only fire once (cache-key gated for 7 days).
 *
 * Cap of 200 reminders per RFQ per run keeps a category with thousands
 * of suppliers from saturating the queue — the rest still see the
 * RFQ in the marketplace listing.
 */
class SendRfqDeadlineRemindersCommand extends Command
{
    protected $signature = 'rfqs:deadline-reminders';

    protected $description = 'Notify suppliers when an RFQ deadline is approaching (48h / 24h / 2h).';

    /** Window in hours we consider "this is the bucket". */
    private const BUCKETS = [
        48 => 60, // 48 ± 60min — fires in the hour the RFQ crosses 48h-out
        24 => 60,
        2 => 30, // tighter window for the urgency tier
    ];

    public function handle(): int
    {
        $now = now();
        $totalNotified = 0;

        foreach (self::BUCKETS as $hours => $windowMinutes) {
            $windowStart = $now->copy()->addHours($hours)->subMinutes($windowMinutes);
            $windowEnd = $now->copy()->addHours($hours)->addMinutes($windowMinutes);

            $rfqs = Rfq::query()
                ->where('status', RfqStatus::OPEN->value)
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [$windowStart, $windowEnd])
                ->get();

            foreach ($rfqs as $rfq) {
                $bidderCompanyIds = $rfq->bids()->pluck('company_id')->all();

                $companyQuery = Company::query()
                    ->where('id', '!=', $rfq->company_id)
                    ->whereNotIn('id', $bidderCompanyIds);

                if ($rfq->category_id) {
                    $companyQuery->whereHas('categories', fn ($q) => $q->where('categories.id', $rfq->category_id));
                }

                $companyIds = $companyQuery->pluck('id');
                if ($companyIds->isEmpty()) {
                    continue;
                }

                $recipients = User::whereIn('company_id', $companyIds)
                    ->limit(200)
                    ->get();

                $sent = 0;
                foreach ($recipients as $user) {
                    $cacheKey = "rfq:deadline:{$rfq->id}:{$hours}:user:{$user->id}";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    $user->notify(new RfqDeadlineReminderNotification($rfq, $hours));
                    Cache::put($cacheKey, true, now()->addDays(7));
                    $sent++;
                }

                $totalNotified += $sent;
            }

            $this->info("Bucket {$hours}h: processed ".$rfqs->count().' RFQ(s).');
        }

        $this->info("Sent {$totalNotified} deadline reminder(s).");

        return self::SUCCESS;
    }
}
