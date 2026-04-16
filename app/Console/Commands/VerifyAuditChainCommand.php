<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Walk the audit_logs table from oldest to newest and verify the hash
 * chain integrity. Phase 0 of the UAE Compliance Roadmap.
 *
 * What it checks per row:
 *   1. The stored `previous_hash` matches the hash of the previous row in
 *      sequence (i.e. the chain isn't forked or broken).
 *   2. The stored `hash` equals what the model would compute from the row's
 *      canonical bytes plus its `previous_hash`. If this fails, the row
 *      was tampered with after creation.
 *
 * Pre-Phase-0 rows have NULL `previous_hash` AND a different `hash` recipe
 * (the legacy implementation hashed only id+action+timestamp). Those legacy
 * rows are skipped — they're flagged as "legacy, unverifiable" so the
 * operator knows where the chain begins. Anything inserted after the
 * Phase 0 migration MUST verify cleanly.
 *
 * Exit codes:
 *   0 — chain intact (or only legacy rows present)
 *   1 — one or more rows failed verification
 *
 * Usage:
 *   php artisan audit:verify-chain
 *   php artisan audit:verify-chain --from=10000 --to=20000
 *   php artisan audit:verify-chain --chunk=500
 */
class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify-chain
        {--from= : Lowest id to include (inclusive)}
        {--to= : Highest id to include (inclusive)}
        {--chunk= : Rows per chunk when walking the table}
        {--quiet-success : Only print on errors}';

    protected $description = 'Verify the integrity of the audit log hash chain';

    public function handle(): int
    {
        $chunk = (int) ($this->option('chunk') ?? config('audit.verify_chain_chunk', 1000));

        // State tracked across chunks: the previous row's hash and id, so
        // each row in the next chunk can be linked back to the last one
        // we processed.
        $previousHash = null;
        $previousId = null;
        $checked = 0;
        $legacy = 0;
        $errors = [];

        $query = AuditLog::query()->orderBy('id');
        if ($from = $this->option('from')) {
            $query->where('id', '>=', (int) $from);
        }
        if ($to = $this->option('to')) {
            $query->where('id', '<=', (int) $to);
        }

        $query->chunk($chunk, function ($logs) use (
            &$previousHash, &$previousId, &$checked, &$legacy, &$errors
        ) {
            foreach ($logs as $log) {
                $checked++;

                // Legacy row detection: anything inserted before the
                // Phase 0 migration has previous_hash = NULL AND its
                // stored hash was computed with the old recipe. We can't
                // re-verify those (the old hash included now() which we
                // didn't persist), so we skip them but DO use them as the
                // chain anchor for the first post-migration row.
                $isLegacy = $log->previous_hash === null
                    && static::looksLikeLegacyHash($log);

                if ($isLegacy) {
                    $legacy++;
                    $previousHash = $log->hash;
                    $previousId = $log->id;

                    continue;
                }

                // Chain link check: this row's previous_hash must match
                // the hash we saw on the prior row. If not, either a row
                // was deleted in between, or the chain forked.
                if ($previousId !== null && $log->previous_hash !== $previousHash) {
                    $errors[] = sprintf(
                        'Row %d: previous_hash mismatch (expected %s, got %s)',
                        $log->id,
                        $this->fmt($previousHash),
                        $this->fmt($log->previous_hash)
                    );
                }

                // Self check: recompute the row's hash from its own
                // canonical bytes + the chain parent. Different result =
                // the row was modified post-insert.
                $expected = AuditLog::computeHash(
                    AuditLog::canonicalize($log->getAttributes()),
                    $log->previous_hash
                );

                if (! hash_equals($expected, (string) $log->hash)) {
                    $errors[] = sprintf(
                        'Row %d: hash mismatch (recomputed %s, stored %s)',
                        $log->id,
                        $this->fmt($expected),
                        $this->fmt($log->hash)
                    );
                }

                $previousHash = $log->hash;
                $previousId = $log->id;
            }
        });

        $this->newLine();

        if ($errors) {
            $this->error(sprintf(
                'Audit chain verification FAILED: %d row(s) checked, %d legacy skipped, %d error(s).',
                $checked, $legacy, count($errors)
            ));
            foreach ($errors as $err) {
                $this->line('  ✗ '.$err);
            }

            return self::FAILURE;
        }

        if (! $this->option('quiet-success')) {
            $this->info(sprintf(
                '✓ Audit chain intact: %d row(s) checked, %d legacy skipped.',
                $checked, $legacy
            ));
            if ($previousId !== null) {
                $this->line('  Chain head: row #'.$previousId.' → '.$this->fmt($previousHash));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Cheap heuristic for "this row was inserted before Phase 0".
     *
     * The legacy hash recipe was hash('sha256', json([user_id, action,
     * resource_type, resource_id, now()->toISOString()])). Since we never
     * persisted the now() value, we can't recompute it. The signal we use:
     * previous_hash IS NULL, AND the stored hash doesn't match the new
     * recipe applied to this row. If both are true, treat as legacy.
     */
    protected static function looksLikeLegacyHash(AuditLog $log): bool
    {
        if ($log->previous_hash !== null) {
            return false;
        }
        $newRecipe = AuditLog::computeHash(
            AuditLog::canonicalize($log->getAttributes()),
            null
        );

        // If the new recipe matches, it's a genuine genesis row (the very
        // first post-Phase-0 row), not legacy.
        return ! hash_equals($newRecipe, (string) $log->hash);
    }

    protected function fmt(?string $hash): string
    {
        return $hash === null ? '<null>' : substr($hash, 0, 12).'…';
    }
}
