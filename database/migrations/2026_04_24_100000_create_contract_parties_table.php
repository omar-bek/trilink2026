<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Hardening — contract_parties junction table.
 *
 * Why: every supplier-side query today goes through
 * `whereJsonContains('parties', ['company_id' => $cid])`. MySQL can't
 * index inside a JSON document, so each query full-scans the
 * contracts table and JSON-parses every row. At <10K contracts the
 * latency is hidden by Eloquent's eager loads; at 100K it tanks the
 * supplier dashboard.
 *
 * Strategy: keep the JSON column as the canonical source of truth
 * (so nothing existing breaks), add a denormalized junction table
 * as a *queryable index*, and sync the two via the ContractObserver.
 * The migration backfills the junction from existing JSON rows so
 * a freshly-migrated database is immediately queryable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            // Role mirrors the JSON column shape: 'buyer', 'supplier',
            // 'logistics', 'clearance', etc. Indexed for the "all
            // suppliers on this contract" query.
            $table->string('role', 32)->nullable();
            $table->timestamps();

            // The most common query is "every contract for company X" —
            // covered by (company_id) index. The reverse — "every
            // party of contract Y" — is covered by the contract_id FK.
            // Adding a UNIQUE on (contract_id, company_id, role) so
            // the observer is naturally idempotent: re-running the
            // sync on a contract just upserts instead of duplicating.
            $table->unique(['contract_id', 'company_id', 'role'], 'contract_parties_unique');
            $table->index(['company_id', 'role'], 'contract_parties_company_role_idx');
        });

        // Backfill from the canonical JSON column. We walk the
        // contracts table in chunks so a 100K-row migration doesn't
        // load everything into memory. The buyer is also inserted
        // explicitly because the JSON `parties` column historically
        // listed only the supplier-side counterparties on some rows.
        if (Schema::hasTable('contracts')) {
            DB::table('contracts')
                ->select(['id', 'buyer_company_id', 'parties'])
                ->orderBy('id')
                ->chunk(500, function ($contracts) {
                    $rows = [];
                    foreach ($contracts as $c) {
                        // Buyer side — always insert.
                        if ($c->buyer_company_id) {
                            $rows[] = [
                                'contract_id' => $c->id,
                                'company_id'  => (int) $c->buyer_company_id,
                                'role'        => 'buyer',
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ];
                        }

                        // Counterparties from the JSON column.
                        $parties = is_string($c->parties) ? json_decode($c->parties, true) : ($c->parties ?? []);
                        if (!is_array($parties)) {
                            continue;
                        }
                        foreach ($parties as $party) {
                            $cid  = $party['company_id'] ?? null;
                            $role = $party['role'] ?? null;
                            if (!$cid || !$role) {
                                continue;
                            }
                            // Skip the buyer if it's already covered above.
                            if ($role === 'buyer' && (int) $cid === (int) $c->buyer_company_id) {
                                continue;
                            }
                            $rows[] = [
                                'contract_id' => $c->id,
                                'company_id'  => (int) $cid,
                                'role'        => $role,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ];
                        }
                    }
                    if ($rows !== []) {
                        // insertOrIgnore so a duplicate (contract, company, role)
                        // tuple from a malformed legacy JSON row doesn't kill the
                        // backfill — the unique index above guarantees the index
                        // stays clean.
                        DB::table('contract_parties')->insertOrIgnore($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_parties');
    }
};
