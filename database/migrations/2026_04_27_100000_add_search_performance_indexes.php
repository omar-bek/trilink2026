<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Phase 6.1 — add indexes on columns used in search, filter,
 * and admin listing queries that were doing full table scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('first_name');
            $table->index('last_name');
            // NOTE: users.status index is already created by the base users
            // migration (0001_01_01_000001_create_users_table.php). Adding it
            // here would fail on fresh installs with "index already exists".
        });

        // Dashboard listings filter by status and sort by newest. Without a
        // composite index MySQL does a filesort after the status filter.
        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'contracts_status_created_idx');
        });

        // RFQ marketplace page: WHERE status='open' ORDER BY deadline ASC.
        Schema::table('rfqs', function (Blueprint $table) {
            $table->index(['status', 'deadline'], 'rfqs_status_deadline_idx');
        });

        // "My bids" screens filter by company_id + status, sorted by newest.
        Schema::table('bids', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'created_at'], 'bids_company_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['first_name']);
            $table->dropIndex(['last_name']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_status_created_idx');
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropIndex('rfqs_status_deadline_idx');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex('bids_company_status_created_idx');
        });
    }
};
