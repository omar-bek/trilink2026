<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Daily reminder for milestone payments that have slipped past their
 * due date. Three escalation tiers — 7 / 14 / 30 days overdue — each
 * fired at most once per (payment × tier) so re-running the command
 * doesn't spam the inbox.
 *
 * Only payments still in PENDING_APPROVAL / APPROVED are considered.
 * Once they reach PROCESSING / COMPLETED there's nothing to chase.
 */
class SendPaymentOverdueRemindersCommand extends Command
{
    protected $signature = 'payments:overdue-reminders';

    protected $description = 'Notify finance approvers about milestone payments that are overdue (7/14/30 days).';

    private const TIERS = [7, 14, 30];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $totalNotified = 0;

        foreach (self::TIERS as $daysOverdue) {
            $targetDate = $today->copy()->subDays($daysOverdue);

            $payments = Payment::query()
                ->whereIn('status', [
                    PaymentStatus::PENDING_APPROVAL->value,
                    PaymentStatus::APPROVED->value,
                ])
                ->whereNotNull('due_date')
                ->whereDate('due_date', $targetDate->toDateString())
                ->with(['contract'])
                ->get();

            foreach ($payments as $payment) {
                $cacheKey = "payment:overdue:{$payment->id}:{$daysOverdue}";
                if (Cache::has($cacheKey)) {
                    continue;
                }

                $companyIds = collect([$payment->company_id, $payment->recipient_company_id])
                    ->filter()
                    ->unique()
                    ->all();
                if (empty($companyIds)) {
                    continue;
                }

                $recipients = User::whereIn('company_id', $companyIds)
                    ->whereIn('role', ['company_manager', 'finance', 'admin'])
                    ->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new PaymentOverdueNotification($payment, $daysOverdue));
                    $totalNotified += $recipients->count();
                }

                Cache::put($cacheKey, true, now()->addDays(120));
            }

            $this->info("Tier {$daysOverdue}d: processed ".$payments->count().' payment(s).');
        }

        $this->info("Sent {$totalNotified} overdue reminder(s).");

        return self::SUCCESS;
    }
}
