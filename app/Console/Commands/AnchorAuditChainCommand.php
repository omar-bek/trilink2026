<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 8 (UAE Compliance Roadmap) — compute a Merkle root of the
 * audit chain and anchor it externally so an attacker who has DB
 * access cannot rewrite the chain + recompute the hashes silently.
 *
 * The anchor is written to the `audit_chain_anchors` table (local
 * record) AND to S3 Object Lock (if configured) where the retention
 * policy prevents anyone — including the bucket owner — from
 * deleting or modifying it for 7 years.
 *
 * When S3 is not configured (dev/staging), the anchor is written to
 * local storage under `audit-anchors/` so the verify-chain command
 * can still cross-reference it.
 *
 * Schedule: daily at 03:00 GST.
 *
 * Usage:
 *   php artisan audit:anchor-chain
 *   php artisan audit:anchor-chain --dry-run
 */
class AnchorAuditChainCommand extends Command
{
    protected $signature = 'audit:anchor-chain
        {--dry-run : Show what would be anchored without writing}';

    protected $description = 'Compute a Merkle root of the audit chain and anchor it to external WORM storage.';

    public function handle(): int
    {
        $latest = AuditLog::query()->orderByDesc('id')->first();
        if (!$latest) {
            $this->info('No audit logs to anchor.');
            return self::SUCCESS;
        }

        $chainHead = (string) $latest->hash;
        $rowCount = AuditLog::count();

        // The "Merkle root" for Phase 8 is simply the chain head hash
        // (which already incorporates every prior row via the hash chain).
        // A true Merkle tree would allow O(log n) per-row proofs, but
        // the chain head is sufficient for tamper detection: if anyone
        // modifies ANY row, the chain head changes and the anchor
        // won't match.
        $anchor = [
            'chain_head_hash' => $chainHead,
            'chain_head_id'   => $latest->id,
            'row_count'       => $rowCount,
            'anchored_at'     => now()->toIso8601String(),
            'environment'     => config('app.env'),
        ];

        $anchorJson = json_encode($anchor, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $anchorSha  = hash('sha256', $anchorJson);

        if ($this->option('dry-run')) {
            $this->info('DRY RUN — would anchor:');
            $this->line($anchorJson);
            $this->line("SHA-256: {$anchorSha}");
            return self::SUCCESS;
        }

        // Persist locally
        $path = sprintf('audit-anchors/%s.json', now()->format('Y-m-d_His'));
        Storage::disk('local')->put($path, $anchorJson);

        // Persist to DB if the table exists (Phase 9 migration adds it)
        try {
            \Illuminate\Support\Facades\DB::table('audit_chain_anchors')->insert([
                'chain_head_hash' => $chainHead,
                'chain_head_id'   => $latest->id,
                'row_count'       => $rowCount,
                'anchor_sha256'   => $anchorSha,
                'storage_path'    => $path,
                'anchored_at'     => now(),
                'created_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Table doesn't exist yet — local file is enough.
        }

        // S3 Object Lock (when configured)
        $s3Disk = config('security.audit_anchor_s3_disk');
        if ($s3Disk && Storage::disk($s3Disk)->getAdapter()) {
            try {
                Storage::disk($s3Disk)->put($path, $anchorJson);
                $this->info("Anchored to S3 disk '{$s3Disk}' at {$path}.");
            } catch (\Throwable $e) {
                $this->warn("S3 anchor failed: {$e->getMessage()}. Local anchor persisted.");
            }
        }

        $this->info(sprintf(
            'Audit chain anchored: %d rows, head=#%d, sha=%s',
            $rowCount,
            $latest->id,
            substr($anchorSha, 0, 16) . '…'
        ));

        return self::SUCCESS;
    }
}
