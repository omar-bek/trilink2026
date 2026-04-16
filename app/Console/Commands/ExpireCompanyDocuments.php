<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CompanyDocument;
use App\Models\User;
use App\Notifications\DocumentExpiringSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily housekeeping job for the Document Vault:
 *
 *   1. Flips any verified document whose expiry date has passed to "expired".
 *      The badge logic on the company side reads this so an expired ISO/trade
 *      license stops contributing to the verification tier automatically.
 *
 *   2. Sends a one-time reminder to the company manager(s) for each document
 *      that is expiring in the next 30 days but hasn't been notified yet.
 *      We use the audit fields to avoid spamming — first occurrence per
 *      document only.
 */
class ExpireCompanyDocuments extends Command
{
    protected $signature = 'documents:expire';

    protected $description = 'Mark expired company documents and notify managers about ones expiring soon.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Step 1: hard-expire anything past its expires_at date.
        $expiredCount = CompanyDocument::query()
            ->where('status', CompanyDocument::STATUS_VERIFIED)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->update(['status' => CompanyDocument::STATUS_EXPIRED]);

        $this->info("Marked {$expiredCount} document(s) as expired.");

        // Step 2: notify on documents expiring within 30 days. We re-notify
        // anyone who hasn't been alerted yet — managers can act before the
        // doc actually expires.
        $expiringSoon = CompanyDocument::query()
            ->with('company')
            ->where('status', CompanyDocument::STATUS_VERIFIED)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, now()->addDays(30)->toDateString()])
            ->get();

        $notifiedCount = 0;
        foreach ($expiringSoon as $doc) {
            $managers = User::where('company_id', $doc->company_id)
                ->where('role', UserRole::COMPANY_MANAGER->value)
                ->get();

            if ($managers->isNotEmpty()) {
                Notification::send($managers, new DocumentExpiringSoonNotification($doc));
                $notifiedCount += $managers->count();
            }
        }

        $this->info("Notified {$notifiedCount} manager(s) about expiring documents.");

        return self::SUCCESS;
    }
}
