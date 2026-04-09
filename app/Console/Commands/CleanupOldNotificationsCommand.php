<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Weekly housekeeping for the `notifications` table. Without this the
 * table grows unbounded — every action on the platform writes a row,
 * and the bell only ever surfaces the most recent few hundred. Old
 * read notifications are pure dead weight.
 *
 * Retention rule:
 *   - read_at IS NOT NULL  AND  read_at < (now - retentionDays)  → delete
 *   - read_at IS NULL                                            → keep forever
 *
 * Unread notifications are NEVER deleted. The user explicitly hasn't
 * acknowledged them yet so dropping them would silently lose
 * something the user expected to find when they next open the bell.
 */
class CleanupOldNotificationsCommand extends Command
{
    protected $signature = 'notifications:cleanup {--days=60 : Drop notifications read more than this many days ago}';
    protected $description = 'Delete read notifications older than the retention window.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('--days must be greater than zero.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        // chunkById to keep memory bounded on tables with millions of
        // rows. Each chunk is deleted as a batch so the operation is
        // O(N/chunk) round-trips instead of N.
        $deleted = 0;
        DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->chunkById(1000, function ($chunk) use (&$deleted) {
                $ids = $chunk->pluck('id')->all();
                $deleted += DatabaseNotification::whereIn('id', $ids)->delete();
            });

        $this->info("Deleted {$deleted} read notification(s) older than {$days} days.");
        return self::SUCCESS;
    }
}
