<?php

namespace App\Console\Commands;

use App\Models\BankGuarantee;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Phase A — daily reminder for bank guarantees approaching expiry.
 *
 * In UAE procurement practice a BG that lapses while the underlying
 * contract is still live is a commercial disaster — the beneficiary
 * loses their security blanket, the applicant (usually the supplier)
 * has to scramble to get a renewal, and the buyer can pause deliveries
 * until the new advice lands. The reminder lets both sides chase the
 * bank 30 / 14 / 7 / 1 days ahead of expiry.
 *
 * Schedule: daily at 03:00 GST via Kernel::schedule.
 *
 * Usage:
 *   php artisan bg:notify-expiring
 *   php artisan bg:notify-expiring --dry-run
 */
class NotifyExpiringBankGuaranteesCommand extends Command
{
    protected $signature = 'bg:notify-expiring
        {--dry-run : Show what would be sent without dispatching}';

    protected $description = 'Notify both parties of bank guarantees approaching expiry (30/14/7/1 days).';

    public function handle(): int
    {
        $thresholds = [30, 14, 7, 1];
        $today = now()->startOfDay();

        $dueCount = 0;
        foreach ($thresholds as $days) {
            $target = $today->copy()->addDays($days)->toDateString();
            $bgs = BankGuarantee::query()
                ->whereIn('status', ['live', 'issued', 'reduced'])
                ->whereDate('expiry_date', $target)
                ->get();

            foreach ($bgs as $bg) {
                $dueCount++;
                if ($this->option('dry-run')) {
                    $this->line("[DRY] BG {$bg->bg_number} expires in {$days} days");
                    continue;
                }

                // Notify every user in both applicant and beneficiary.
                $companyIds = array_filter([$bg->applicant_company_id, $bg->beneficiary_company_id]);
                $recipients = User::whereIn('company_id', $companyIds)->active()->get();
                foreach ($recipients as $user) {
                    $user->notify(new \App\Notifications\DatabaseOnlyNotification(
                        title: __('bg.notify.expiring_title', ['days' => $days]),
                        body: __('bg.notify.expiring_body', [
                            'bg' => $bg->bg_number,
                            'type' => __('bg.type.'.$bg->type->value),
                            'date' => $bg->expiry_date->format('d M Y'),
                        ]),
                        url: route('dashboard.bank-guarantees.show', ['id' => $bg->id]),
                    ));
                }

                // Append an event row for audit.
                \App\Models\BankGuaranteeEvent::create([
                    'bank_guarantee_id' => $bg->id,
                    'actor_user_id' => null,
                    'event' => 'expiry_notified',
                    'metadata' => ['threshold_days' => $days],
                    'created_at' => now(),
                ]);
            }
        }

        $this->info("Processed {$dueCount} bank guarantee reminder(s).");

        return self::SUCCESS;
    }
}
