<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL FULLTEXT indexes used by the App\Concerns\Searchable scope.
 *
 * Phase 0 / task 0.3 — replaces the old `LIKE %q%` table scans on the
 * busiest list pages with proper FULLTEXT index usage. The Searchable
 * trait detects each index at runtime and falls back to LIKE on
 * sqlite/pgsql or when an index is missing, so this migration is a
 * MySQL-only optimisation; it's a no-op everywhere else.
 *
 * Indexes are added one-at-a-time inside try/catch so a partial run
 * (e.g. on a fresh sqlite dev box) doesn't bring down the migrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->safeAddFullText('rfqs', ['title', 'rfq_number']);
        $this->safeAddFullText('contracts', ['title', 'contract_number']);
        $this->safeAddFullText('purchase_requests', ['title', 'pr_number']);
        $this->safeAddFullText('companies', ['name', 'name_ar', 'email', 'registration_number']);
        $this->safeAddFullText('users', ['first_name', 'last_name', 'email']);
        $this->safeAddFullText('products', ['name', 'description', 'sku']);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->safeDropIndex('rfqs', 'rfqs_search_ft');
        $this->safeDropIndex('contracts', 'contracts_search_ft');
        $this->safeDropIndex('purchase_requests', 'purchase_requests_search_ft');
        $this->safeDropIndex('companies', 'companies_search_ft');
        $this->safeDropIndex('users', 'users_search_ft');
        $this->safeDropIndex('products', 'products_search_ft');
    }

    private function safeAddFullText(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Skip columns that don't exist on this build (some columns are
        // added by later sprints; we don't want migration to choke).
        $existing = array_values(array_filter(
            $columns,
            fn ($c) => Schema::hasColumn($table, $c)
        ));

        if ($existing === []) {
            return;
        }

        $indexName = $table.'_search_ft';

        try {
            Schema::table($table, function ($t) use ($existing, $indexName) {
                $t->fullText($existing, $indexName);
            });
        } catch (Throwable $e) {
            // Index already exists or unsupported engine — log and continue.
            report($e);
        }
    }

    private function safeDropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function ($t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }
};
