<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\LateFeeAccrualService;
use Illuminate\Console\Command;

/**
 * Daily cron — turns the theoretical late fee on overdue payments into
 * real PENDING_APPROVAL rows the finance team can chase. Idempotent per
 * (parent_payment_id, year-month) via the service.
 */
class AccrueLateFees extends Command
{
    protected $signature = 'payments:accrue-late-fees';

    protected $description = 'Create a late-fee Payment row for each overdue payment this month.';

    public function handle(LateFeeAccrualService $service): int
    {
        $created = 0;

        Payment::query()
            ->whereIn('status', [PaymentStatus::PENDING_APPROVAL->value, PaymentStatus::APPROVED->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('is_late_fee_accrual', false)
            ->cursor()
            ->each(function (Payment $p) use ($service, &$created) {
                if ($service->accrueFor($p->fresh(['contract']))) {
                    $created++;
                }
            });

        $this->info(sprintf('Accrued %d late-fee payment row(s).', $created));

        return self::SUCCESS;
    }
}
