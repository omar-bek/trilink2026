<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Archive cold audit_logs rows to immutable storage.
 *
 * Phase 0 ships this as a SKELETON: it counts the eligible rows and
 * reports them, but doesn't actually move anything off the live table
 * because the WORM destination (S3 Object Lock + OpenTimestamps anchor)
 * is built in Phase 8 of the UAE Compliance Roadmap.
 *
 * Why ship the skeleton now: it lets us register the command in the
 * scheduler today, surface the row counts in operational dashboards, and
 * make sure the cutoff math is correct against the retention policy in
 * config/audit.php. When Phase 8 lands, the only change is to fill in
 * the archive backend — the entry point and operator UX stay the same.
 *
 * Defaults:
 *   --before  → today − config('audit.retention_days')
 *   --dry-run → forced ON until Phase 8 wires up a real backend
 */
class ArchiveAuditLogsCommand extends Command
{
    protected $signature = 'audit:archive
        {--before= : Archive rows older than this date (YYYY-MM-DD)}
        {--dry-run : Report only, do not move or delete any row}';

    protected $description = 'Archive expired audit_logs rows to immutable storage';

    public function handle(): int
    {
        $retentionDays = (int) config('audit.retention_days', 2555);
        $cutoff = $this->option('before')
            ? Carbon::parse($this->option('before'))->startOfDay()
            : now()->subDays($retentionDays)->startOfDay();

        $count = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line(sprintf(
            'Retention policy: %d days (config/audit.php)',
            $retentionDays
        ));
        $this->line('Cutoff date: ' . $cutoff->toDateString());
        $this->line('Eligible rows for archive: ' . number_format($count));

        if ($count === 0) {
            $this->info('Nothing to archive.');
            return self::SUCCESS;
        }

        $backend = config('audit.archive_backend');

        if ($this->option('dry-run') || empty($backend)) {
            $this->newLine();
            $this->warn('NO ARCHIVE BACKEND CONFIGURED — running in dry mode.');
            $this->line('To enable archival to S3 Object Lock + OpenTimestamps,');
            $this->line('see Phase 8 of docs/UAE_COMPLIANCE_ROADMAP.md.');
            $this->line('Set AUDIT_ARCHIVE_BACKEND in .env when ready.');
            return self::SUCCESS;
        }

        // Phase 8 will fill this in:
        //   - Stream eligible rows to the configured backend in batches
        //   - Compute Merkle root over the archived batch
        //   - Anchor the root to OpenTimestamps and store the receipt
        //   - Soft-delete the rows on success (hard delete after grace period)
        //   - Audit-log the archival itself (meta-audit)
        $this->error(sprintf(
            'Archive backend "%s" is configured but the archival pipeline is not yet implemented (Phase 8).',
            $backend
        ));

        return self::FAILURE;
    }
}
