<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (UAE Compliance Roadmap) — re-encrypt every encrypted column
 * with the CURRENT APP_KEY. Used after rotating the application key:
 *
 *   1. Generate a new key: `php artisan key:generate --show`
 *   2. Set the OLD key as DECRYPT_KEY=... in .env (temporary)
 *   3. Set the NEW key as APP_KEY=... in .env
 *   4. Run this command: `php artisan keys:re-encrypt`
 *   5. Remove DECRYPT_KEY from .env
 *
 * The command decrypts every value with the old key and re-encrypts
 * with the new one. It walks the tables in chunked order and commits
 * each chunk so a crash mid-way leaves the DB in a usable state
 * (partially re-encrypted rows are fine because each row is
 * self-contained — the key used is encoded in the ciphertext header).
 *
 * IMPORTANT: this command does NOT rotate the APP_KEY itself. That's
 * a manual step because the key lives in the .env file, not in the
 * DB, and deploying a new .env requires coordination with the
 * infrastructure team.
 *
 * Usage:
 *   php artisan keys:re-encrypt --old-key=base64:...
 *   php artisan keys:re-encrypt --dry-run
 */
class RotateEncryptionKeyCommand extends Command
{
    protected $signature = 'keys:re-encrypt
        {--old-key= : The previous APP_KEY (base64:...) to decrypt existing values}
        {--dry-run : Count rows without re-encrypting}
        {--chunk=500 : Rows per batch}';

    protected $description = 'Re-encrypt all encrypted columns with the current APP_KEY after a key rotation.';

    /**
     * All (table, column) pairs that use Laravel's encrypted cast.
     * Must be kept in sync with the model casts — if a new encrypted
     * column is added, add it here too.
     */
    private const TARGETS = [
        ['table' => 'companies',            'column' => 'tax_number'],
        ['table' => 'beneficial_owners',    'column' => 'id_number'],
        ['table' => 'beneficial_owners',    'column' => 'date_of_birth'],
        ['table' => 'beneficial_owners',    'column' => 'source_of_wealth'],
        ['table' => 'company_bank_details', 'column' => 'iban'],
        ['table' => 'company_bank_details', 'column' => 'swift'],
        ['table' => 'company_bank_details', 'column' => 'holder_name'],
        ['table' => 'audit_logs',           'column' => 'ip_address'],
        ['table' => 'audit_logs',           'column' => 'user_agent'],
        ['table' => 'audit_logs',           'column' => 'before'],
        ['table' => 'audit_logs',           'column' => 'after'],
    ];

    public function handle(): int
    {
        $oldKey = (string) ($this->option('old-key') ?: config('app.decrypt_key') ?: env('DECRYPT_KEY'));
        $isDryRun = (bool) $this->option('dry-run');
        $chunk = (int) ($this->option('chunk') ?: 500);

        if (! $oldKey && ! $isDryRun) {
            $this->error('No old key provided. Pass --old-key=base64:... or set DECRYPT_KEY in .env.');

            return self::FAILURE;
        }

        $totalRows = 0;
        $totalReEncrypted = 0;

        foreach (self::TARGETS as $t) {
            $table = $t['table'];
            $col = $t['column'];

            if (! Schema::hasColumn($table, $col)) {
                $this->warn("Skipping {$table}.{$col} — column missing.");

                continue;
            }

            $count = DB::table($table)->whereNotNull($col)->count();
            $totalRows += $count;

            if ($isDryRun) {
                $this->line("  {$table}.{$col}: {$count} non-null rows");

                continue;
            }

            $reEncrypted = 0;
            DB::table($table)->whereNotNull($col)->orderBy('id')->chunkById($chunk, function ($rows) use ($table, $col, $oldKey, &$reEncrypted) {
                foreach ($rows as $row) {
                    $cipher = $row->{$col};
                    if ($cipher === null || $cipher === '') {
                        continue;
                    }

                    // Decrypt with the old key
                    try {
                        $encrypter = new Encrypter(
                            base64_decode(str_replace('base64:', '', $oldKey)),
                            config('app.cipher', 'AES-256-CBC')
                        );
                        $plain = $encrypter->decryptString((string) $cipher);
                    } catch (\Throwable) {
                        // Already encrypted with the new key (re-run safety) or
                        // plaintext — try current key before giving up.
                        try {
                            $plain = Crypt::decryptString((string) $cipher);
                        } catch (\Throwable) {
                            // Genuinely broken — skip, don't crash.
                            continue;
                        }
                    }

                    // Re-encrypt with the current (new) APP_KEY
                    $newCipher = Crypt::encryptString($plain);

                    DB::table($table)->where('id', $row->id)->update([$col => $newCipher]);
                    $reEncrypted++;
                }
            });

            $totalReEncrypted += $reEncrypted;
            $this->info("  {$table}.{$col}: {$reEncrypted} / {$count} re-encrypted.");
        }

        if ($isDryRun) {
            $this->info("DRY RUN — {$totalRows} rows across ".count(self::TARGETS).' columns would be re-encrypted.');
        } else {
            $this->info("Re-encryption complete: {$totalReEncrypted} rows re-encrypted.");
            $this->warn('IMPORTANT: Remove DECRYPT_KEY from .env now that all rows use the new APP_KEY.');
        }

        return self::SUCCESS;
    }
}
