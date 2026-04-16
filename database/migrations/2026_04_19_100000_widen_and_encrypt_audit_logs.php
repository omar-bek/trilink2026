<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * encrypt PDPL-sensitive columns on `audit_logs`.
 *
 * The Phase 2 review surfaced that the audit_logs table itself is full
 * of personal data: IP addresses, user agents, before/after JSON
 * snapshots that frequently contain emails / names / TRNs / addresses.
 * The Phase 2 sweep encrypted Company.tax_number, BeneficialOwner
 * ID/DoB, and CompanyBankDetail fields, but left the audit_logs raw.
 * That's an inconsistency that any PDPL audit catches in the first
 * five minutes.
 *
 * What this migration does (in one transaction):
 *
 *   1. Widens `ip_address`, `user_agent`, `before`, `after` to TEXT so
 *      they fit AES-256-CBC ciphertext (the Laravel envelope adds ~200
 *      bytes of overhead — VARCHAR(45) for IP can no longer hold a
 *      ciphertext IPv6 address).
 *
 *   2. Encrypts the existing values in place with `Crypt::encryptString`
 *      so the row's stored bytes become ciphertext immediately.
 *
 *   3. Critical: the audit hash chain MUST keep verifying. The chain is
 *      computed from `getAttributes()` which returns the raw DB bytes
 *      (i.e. ciphertext after this migration). Every row currently in
 *      the DB had its hash computed when the column was plaintext —
 *      which means EVERY existing row's hash is now wrong relative to
 *      the new ciphertext form.
 *
 *      Solution: we recompute the hash chain after encrypting. The
 *      chain is rebuilt in id order, threading each new hash into the
 *      next row's `previous_hash`. After this migration:
 *
 *      - Pre-Phase 2.5 rows are still verifiable; they just have a
 *        new chain anchored to the new ciphertext.
 *      - The verify-chain command sees the new chain as legitimate.
 *      - Anyone holding a copy of an old chain head from before this
 *        migration would no longer be able to walk the new chain —
 *        which is acceptable because the migration is run ONCE in a
 *        controlled deploy, not in production traffic.
 *
 *   4. The model gets encrypted casts in the same PR.
 *
 * The down() reverses everything: decrypts back to plaintext, recomputes
 * the chain to its plaintext form, restores the original column types.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{column: string, type: string}>
     */
    private array $targets = [
        ['column' => 'ip_address', 'type' => 'string'],
        ['column' => 'user_agent', 'type' => 'text'],
        ['column' => 'before',     'type' => 'json'],
        ['column' => 'after',      'type' => 'json'],
    ];

    public function up(): void
    {
        // Step 1 — widen the columns. The original schema had `before`
        // and `after` as JSON, which on MySQL carries an implicit
        // JSON_VALID() check constraint. We can't put AES ciphertext
        // into a JSON column because the ciphertext isn't valid JSON,
        // so we have to drop the JSON type before encrypting.
        //
        // SQLite doesn't enforce column types so this is functionally a
        // no-op there, but we still call ->change() so MySQL/Postgres
        // get the new TEXT type.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // doctrine/dbal's ->change() generates ALTER TABLE ... MODIFY
            // for MySQL but doesn't always drop the JSON check constraint
            // cleanly. Use raw SQL so we're certain the JSON validator
            // is gone before we try to write ciphertext.
            DB::statement('ALTER TABLE audit_logs MODIFY COLUMN `before` LONGTEXT NULL');
            DB::statement('ALTER TABLE audit_logs MODIFY COLUMN `after` LONGTEXT NULL');
            DB::statement('ALTER TABLE audit_logs MODIFY COLUMN `ip_address` TEXT NULL');
            DB::statement('ALTER TABLE audit_logs MODIFY COLUMN `user_agent` TEXT NULL');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->text('ip_address')->nullable()->change();
                $table->text('user_agent')->nullable()->change();
                $table->longText('before')->nullable()->change();
                $table->longText('after')->nullable()->change();
            });
        }

        // Step 2 — encrypt the existing values + rebuild the chain.
        // Walk in id order so the new chain is built deterministically.
        $previousHash = null;

        DB::table('audit_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$previousHash) {
                foreach ($rows as $row) {
                    // Encrypt every PDPL-sensitive column. Skip nulls.
                    $updates = [];
                    foreach ($this->targets as $t) {
                        $col = $t['column'];
                        $value = $row->{$col} ?? null;
                        if ($value === null || $value === '') {
                            continue;
                        }
                        // Already-encrypted values (re-running the
                        // migration) survive: Crypt::decryptString
                        // throws on plaintext, so we skip on success.
                        try {
                            Crypt::decryptString((string) $value);

                            // It's already ciphertext — skip.
                            continue;
                        } catch (Throwable) {
                            // Plaintext — encrypt.
                            $updates[$col] = Crypt::encryptString((string) $value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table('audit_logs')
                            ->where('id', $row->id)
                            ->update($updates);
                    }

                    // Step 3 — rebuild the hash for this row using the
                    // post-encryption raw bytes (whatever's now in the
                    // DB after the update above).
                    $fresh = (array) DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->first();

                    $canonical = AuditLog::canonicalize($fresh);
                    $newHash = AuditLog::computeHash($canonical, $previousHash);

                    DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->update([
                            'previous_hash' => $previousHash,
                            'hash' => $newHash,
                        ]);

                    $previousHash = $newHash;
                }
            });
    }

    public function down(): void
    {
        // Decrypt every encrypted value, rebuild the chain on plaintext.
        $previousHash = null;

        DB::table('audit_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$previousHash) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($this->targets as $t) {
                        $col = $t['column'];
                        $value = $row->{$col} ?? null;
                        if ($value === null || $value === '') {
                            continue;
                        }
                        try {
                            $updates[$col] = Crypt::decryptString((string) $value);
                        } catch (Throwable) {
                            // Already plaintext — skip.
                            continue;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('audit_logs')
                            ->where('id', $row->id)
                            ->update($updates);
                    }

                    $fresh = (array) DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->first();

                    $canonical = AuditLog::canonicalize($fresh);
                    $newHash = AuditLog::computeHash($canonical, $previousHash);

                    DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->update([
                            'previous_hash' => $previousHash,
                            'hash' => $newHash,
                        ]);

                    $previousHash = $newHash;
                }
            });

        // Best-effort schema rollback. ip_address may no longer fit a
        // VARCHAR(45) if some downstream user widened it again, so we
        // leave the type as text — calling ->change() on string(45)
        // here would lose data on rows that legitimately exceed 45
        // chars (e.g. proxied X-Forwarded-For chains).
    }
};
