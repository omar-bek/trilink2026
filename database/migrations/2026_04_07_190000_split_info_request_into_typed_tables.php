<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 / task 0.6 — splits the `info_request` JSON junk drawer on the
 * companies table into two typed tables:
 *
 *   - company_info_requests: a single active "admin wants more info"
 *     request. One row per company (unique company_id). Cleared by the
 *     RegisterController re-submission flow the same way the JSON used
 *     to be set to null.
 *
 *   - company_bank_details: permanent bank details used by the Settings
 *     page to collect IBAN / SWIFT / holder name. One row per company.
 *
 * The migration backfills both tables from any existing `info_request`
 * JSON, then drops the legacy column. A down() path recreates the
 * column and writes the rows back into it so rollbacks are lossless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_info_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->json('items')->nullable();                  // ["tax_number", "trade_license_file", ...]
            $t->text('note')->nullable();                   // Free-text admin instructions
            $t->timestamp('requested_at')->nullable();
            $t->unsignedBigInteger('requested_by')->nullable(); // admin user id
            $t->timestamp('responded_at')->nullable();
            $t->unsignedBigInteger('responded_by')->nullable();
            $t->timestamps();

            $t->unique('company_id', 'company_info_requests_company_unique');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('company_bank_details', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->string('holder_name')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('branch')->nullable();
            $t->string('iban', 64)->nullable();
            $t->string('swift', 32)->nullable();
            $t->string('currency', 8)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique('company_id', 'company_bank_details_company_unique');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->index('iban', 'company_bank_details_iban_idx');
            $t->index('swift', 'company_bank_details_swift_idx');
        });

        // Backfill: walk every existing row and decompose its JSON blob.
        if (Schema::hasColumn('companies', 'info_request')) {
            DB::table('companies')
                ->whereNotNull('info_request')
                ->select(['id', 'info_request'])
                ->orderBy('id')
                ->each(function ($row) {
                    $blob = $this->decodeJson($row->info_request);
                    if (! is_array($blob)) {
                        return;
                    }

                    // Split #1: active info-request lifecycle row.
                    $items        = $blob['items']        ?? null;
                    $note         = $blob['note']         ?? null;
                    $requestedAt  = $blob['requested_at'] ?? null;
                    $requestedBy  = $blob['requested_by'] ?? null;
                    if ($items || $note || $requestedAt) {
                        DB::table('company_info_requests')->updateOrInsert(
                            ['company_id' => $row->id],
                            [
                                'items'        => $items ? json_encode($items) : null,
                                'note'         => $note,
                                'requested_at' => $requestedAt,
                                'requested_by' => $requestedBy,
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ]
                        );
                    }

                    // Split #2: bank details.
                    $bank = $blob['bank_details'] ?? null;
                    if (is_array($bank) && $bank !== []) {
                        DB::table('company_bank_details')->updateOrInsert(
                            ['company_id' => $row->id],
                            [
                                'holder_name' => $bank['holder_name'] ?? $bank['account_holder'] ?? null,
                                'bank_name'   => $bank['bank_name']   ?? $bank['bank']           ?? null,
                                'branch'      => $bank['branch']      ?? null,
                                'iban'        => $bank['iban']        ?? null,
                                'swift'       => $bank['swift']       ?? $bank['bic']            ?? null,
                                'currency'    => $bank['currency']    ?? null,
                                'notes'       => $bank['notes']       ?? null,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]
                        );
                    }
                });

            // Finally drop the old junk-drawer column.
            Schema::table('companies', function (Blueprint $t) {
                $t->dropColumn('info_request');
            });
        }
    }

    public function down(): void
    {
        // Re-add the column first so we can rehydrate.
        if (! Schema::hasColumn('companies', 'info_request')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->json('info_request')->nullable()->after('description');
            });
        }

        if (Schema::hasTable('company_info_requests') && Schema::hasTable('company_bank_details')) {
            $companyIds = DB::table('company_info_requests')->pluck('company_id')
                ->merge(DB::table('company_bank_details')->pluck('company_id'))
                ->unique()
                ->values();

            foreach ($companyIds as $cid) {
                $ir = DB::table('company_info_requests')->where('company_id', $cid)->first();
                $bk = DB::table('company_bank_details')->where('company_id', $cid)->first();

                $blob = [];
                if ($ir) {
                    $blob['items']        = $ir->items ? $this->decodeJson($ir->items) : [];
                    $blob['note']         = $ir->note;
                    $blob['requested_at'] = $ir->requested_at;
                    $blob['requested_by'] = $ir->requested_by;
                }
                if ($bk) {
                    $blob['bank_details'] = array_filter([
                        'holder_name' => $bk->holder_name,
                        'bank_name'   => $bk->bank_name,
                        'branch'      => $bk->branch,
                        'iban'        => $bk->iban,
                        'swift'       => $bk->swift,
                        'currency'    => $bk->currency,
                        'notes'       => $bk->notes,
                    ], fn ($v) => $v !== null && $v !== '');
                }

                DB::table('companies')->where('id', $cid)->update([
                    'info_request' => $blob === [] ? null : json_encode($blob),
                ]);
            }
        }

        Schema::dropIfExists('company_bank_details');
        Schema::dropIfExists('company_info_requests');
    }

    private function decodeJson($value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }
};
