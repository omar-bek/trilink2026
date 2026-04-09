<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractSignatureExpiredNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily — finds contracts that have been sitting in PENDING_SIGNATURES
 * past their signing window (default 14 days from creation) and tells
 * both parties the window has closed. Doesn't auto-cancel the
 * contract — that's a buyer decision and they may want to extend the
 * window via a fresh signature request instead.
 */
class ExpireSignatureWindowsCommand extends Command
{
    protected $signature = 'contracts:expire-signature-windows {--days=14 : Signature window length in days}';
    protected $description = 'Notify parties when a contract signing window has elapsed without all signatures.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $totalNotified = 0;

        $contracts = Contract::query()
            ->where('status', ContractStatus::PENDING_SIGNATURES->value)
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($contracts as $contract) {
            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->all();
            if (empty($partyCompanyIds)) {
                continue;
            }

            $recipients = User::whereIn('company_id', $partyCompanyIds)->get();
            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, new ContractSignatureExpiredNotification($contract));
            $totalNotified += $recipients->count();
        }

        $this->info("Processed " . $contracts->count() . " expired signature window(s).");
        $this->info("Sent {$totalNotified} expired-signature notification(s).");
        return self::SUCCESS;
    }
}
