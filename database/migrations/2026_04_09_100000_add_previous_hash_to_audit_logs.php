<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 — Audit Log tamper-evidence: chain hashing.
 *
 * Until now, every audit_logs row carried a SHA-256 fingerprint of itself
 * but the rows weren't linked. Deleting or rewriting one row left the rest
 * of the table intact and unchanged — there was nothing to detect tampering.
 *
 * This migration adds `previous_hash`, the parent pointer in a hash chain.
 * Combined with a deterministic hash recipe in AuditLog::booted(), this turns
 * the table into an append-only ledger: any rewrite breaks the link to every
 * subsequent row, and `php artisan audit:verify-chain` will surface it.
 *
 * Existing rows are left with previous_hash = NULL. Forward chaining starts
 * with the first new row inserted after this migration runs. The verify
 * command treats NULL as "genesis or pre-chain" and continues from there
 * without flagging the legacy gap as tampering.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable()->after('hash');
            $table->index('previous_hash', 'idx_audit_logs_previous_hash');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_previous_hash');
            $table->dropColumn('previous_hash');
        });
    }
};
