<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\IcvCertificate;
use App\Models\User;
use App\Notifications\IcvCertificateExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 4.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * daily reminder for ICV certificates approaching expiry.
 *
 * Schedule: daily, off-hours (recommended 03:00 GST). The command:
 *
 *   1. Walks every verified certificate
 *   2. Computes days_until_expiry
 *   3. Picks the highest reminder threshold ≤ days_until_expiry that
 *      hasn't been sent yet (60 → 30 → 7)
 *   4. Sends IcvCertificateExpiringNotification to every company
 *      manager of the supplier
 *   5. Stamps `last_expiry_reminder_threshold` to prevent re-sending
 *      the same threshold twice
 *
 * Already-expired certificates fall through (the daily expiry-flip
 * job handles them separately). Pending / rejected certificates are
 * skipped — only verified rows participate in bid scoring, so only
 * verified rows are worth reminding about.
 *
 * Usage:
 *   php artisan icv:notify-expiring
 *   php artisan icv:notify-expiring --dry-run
 */
class NotifyExpiringIcvCertificatesCommand extends Command
{
    protected $signature = 'icv:notify-expiring
        {--dry-run : Show what would be sent without dispatching}';

    protected $description = 'Send 60/30/7-day expiry reminders to suppliers holding ICV certificates that are about to expire.';

    /**
     * Threshold ladder, descending. Order matters: we want to send the
     * 60-day reminder first when crossing into the 60-day window, then
     * the 30-day, then the 7-day. Each row's `last_expiry_reminder_threshold`
     * tracks the LOWEST (most recently crossed) threshold that's been
     * sent — so when we see a cert with last=60 and days=29, we know
     * the 30 reminder is the next one due.
     */
    private const THRESHOLDS = [60, 30, 7];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = now()->startOfDay();
        $maxThreshold = max(self::THRESHOLDS);
        $cutoff = $today->copy()->addDays($maxThreshold)->endOfDay();

        $candidates = IcvCertificate::query()
            ->where('status', IcvCertificate::STATUS_VERIFIED)
            ->whereNotNull('expires_date')
            ->where('expires_date', '>', $today)
            ->where('expires_date', '<=', $cutoff)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No ICV certificates within the 60-day reminder window.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($candidates as $cert) {
            $daysUntil = (int) $today->diffInDays($cert->expires_date, false);

            // Find the highest threshold this cert has crossed that
            // hasn't been sent yet. Iterate descending so we always
            // pick the FIRST due reminder, not the smallest.
            $dueThreshold = null;
            foreach (self::THRESHOLDS as $threshold) {
                if ($daysUntil <= $threshold) {
                    // Already sent this threshold? Skip.
                    $alreadySent = $cert->last_expiry_reminder_threshold !== null
                        && (int) $cert->last_expiry_reminder_threshold <= $threshold;
                    if ($alreadySent) {
                        continue;
                    }
                    $dueThreshold = $threshold;
                    break;
                }
            }

            if ($dueThreshold === null) {
                $skipped++;
                continue;
            }

            // Recipients: every company manager of the supplier. The
            // ICV certificate is owned by the company; the user-side
            // notification goes to whoever can act on it.
            $managers = User::query()
                ->where('company_id', $cert->company_id)
                ->where('role', UserRole::COMPANY_MANAGER->value)
                ->get();

            if ($managers->isEmpty()) {
                $this->warn(sprintf(
                    'Certificate #%d (company %d) has no managers to notify.',
                    $cert->id,
                    $cert->company_id
                ));
                continue;
            }

            if ($isDryRun) {
                $this->line(sprintf(
                    '  [dry-run] would notify %d manager(s) for cert #%d (%s, %d days, threshold=%d)',
                    $managers->count(),
                    $cert->id,
                    $cert->issuer,
                    $daysUntil,
                    $dueThreshold
                ));
            } else {
                Notification::send(
                    $managers,
                    new IcvCertificateExpiringNotification($cert, $daysUntil)
                );
                $cert->update(['last_expiry_reminder_threshold' => $dueThreshold]);
                $sent++;
            }
        }

        $this->info(sprintf(
            'ICV expiry reminders%s: sent %d, skipped %d.',
            $isDryRun ? ' (dry run)' : '',
            $sent,
            $skipped
        ));

        return self::SUCCESS;
    }
}
