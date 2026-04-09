<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (UAE Compliance Roadmap) — encrypt PDPL-sensitive columns at rest.
 *
 * Federal Decree-Law 45/2021 Article 20 requires "appropriate technical
 * measures" for personal data, with the level scaled to the sensitivity.
 * For B2B procurement the genuinely sensitive personal fields are:
 *
 *   companies.tax_number              (TRN — fiscal identifier)
 *   beneficial_owners.id_number       (Emirates ID / passport number)
 *   beneficial_owners.date_of_birth   (used to derive age, special category)
 *   beneficial_owners.source_of_wealth (financial profile, AML-sensitive)
 *   company_bank_details.iban         (financial account)
 *   company_bank_details.swift        (financial routing)
 *   company_bank_details.holder_name  (links account to natural person)
 *
 * This migration does THREE things in one transaction:
 *
 *   1. Widens the columns so they can hold the AES-256-CBC ciphertext
 *      that Laravel produces. The Laravel `encrypted` cast outputs ~200-
 *      300 chars for short payloads (because of the IV + MAC + base64
 *      framing), so VARCHAR(64) overflows. We move them to TEXT.
 *
 *   2. Encrypts the existing values in place using Crypt::encryptString
 *      (which is what the Eloquent cast uses internally). After this
 *      runs, every existing row has ciphertext in the column.
 *
 *   3. The Eloquent casts in the corresponding models (Company,
 *      BeneficialOwner, CompanyBankDetail) get added in the same PR
 *      so reads-after-migration go through the cast and decrypt
 *      transparently.
 *
 * The `down()` reverses everything: decrypts and shrinks back to the
 * original column widths. Useful for staging rollback. Production
 * rollback would still need the model casts removed in the same deploy.
 *
 * SQLite caveat: SQLite doesn't enforce column lengths so the widen
 * step is a no-op there, but the encrypt step still runs (which is what
 * the test suite exercises).
 */
return new class extends Migration {
    /**
     * @var array<int, array{table: string, column: string, type: string, length?: int|null}>
     */
    private array $targets = [
        ['table' => 'companies',           'column' => 'tax_number',       'type' => 'string', 'length' => 100],
        ['table' => 'beneficial_owners',   'column' => 'id_number',        'type' => 'string', 'length' => 64],
        ['table' => 'beneficial_owners',   'column' => 'date_of_birth',    'type' => 'date'],
        ['table' => 'beneficial_owners',   'column' => 'source_of_wealth', 'type' => 'text'],
        ['table' => 'company_bank_details','column' => 'iban',             'type' => 'string', 'length' => 64],
        ['table' => 'company_bank_details','column' => 'swift',            'type' => 'string', 'length' => 32],
        ['table' => 'company_bank_details','column' => 'holder_name',      'type' => 'string', 'length' => 255],
    ];

    public function up(): void
    {
        // Step 1 — widen the columns. We always go to TEXT regardless of
        // the original type so the encrypted ciphertext fits and so the
        // schema is consistent across all the encrypted columns.
        foreach ($this->targets as $t) {
            if (!Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                // change() requires doctrine/dbal in Laravel <11; in 11
                // it's native. We use ->change() and assume modern stack.
                $table->text($t['column'])->nullable()->change();
            });
        }

        // Step 2 — encrypt the existing plaintext values in place.
        foreach ($this->targets as $t) {
            if (!Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            DB::table($t['table'])
                ->whereNotNull($t['column'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($t) {
                    foreach ($rows as $row) {
                        $plain = $row->{$t['column']};
                        if ($plain === null || $plain === '') {
                            continue;
                        }
                        // For date columns, normalise the existing value to
                        // an ISO date string before encrypting so the model
                        // cast (encrypted:date) can parse it back. The DB
                        // already stores dates as YYYY-MM-DD strings, so
                        // this is mostly a safety conversion.
                        if ($t['type'] === 'date') {
                            $plain = (string) $plain;
                        }
                        DB::table($t['table'])
                            ->where('id', $row->id)
                            ->update([$t['column'] => Crypt::encryptString((string) $plain)]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Step 1 — decrypt back to plaintext.
        foreach ($this->targets as $t) {
            if (!Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            DB::table($t['table'])
                ->whereNotNull($t['column'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($t) {
                    foreach ($rows as $row) {
                        $cipher = $row->{$t['column']};
                        if ($cipher === null || $cipher === '') {
                            continue;
                        }
                        try {
                            $plain = Crypt::decryptString((string) $cipher);
                        } catch (\Throwable $e) {
                            // Already plaintext (partial migration?) — skip.
                            continue;
                        }
                        DB::table($t['table'])
                            ->where('id', $row->id)
                            ->update([$t['column'] => $plain]);
                    }
                });
        }

        // Step 2 — shrink the columns back. Best-effort: we don't know
        // exactly the original max length per row, so we restore the
        // original schema declarations.
        foreach ($this->targets as $t) {
            if (!Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            Schema::table($t['table'], function (Blueprint $table) use ($t) {
                if ($t['type'] === 'date') {
                    $table->date($t['column'])->nullable()->change();
                } elseif ($t['type'] === 'text') {
                    $table->text($t['column'])->nullable()->change();
                } else {
                    $table->string($t['column'], $t['length'] ?? 255)->nullable()->change();
                }
            });
        }
    }
};
