<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (UAE Compliance Roadmap) — Qualified e-Signature & UAE Pass.
 *
 * Adds the signature-grade fields the resolver needs to enforce
 * Federal Decree-Law 46/2021 grade requirements per contract:
 *
 *   signature_grade_required — pre-computed result of the resolver
 *      (simple | advanced | qualified). Cached on the row so the
 *      contract show page doesn't have to re-resolve on every request
 *      and so an admin can override it manually for edge cases.
 *
 * The per-row signature payload extensions (uae_pass_user_id,
 * tsp_provider, tsp_certificate_id, signature_format, signature_payload)
 * are stamped INSIDE the existing `signatures` JSON column rather than
 * as new top-level columns — that's where the rest of the signature
 * audit trail already lives. Adding them as siblings to user_id /
 * company_id / contract_hash keeps the audit row self-contained.
 *
 * Backwards compatibility: existing contracts get
 * `signature_grade_required = NULL` and the resolver computes the
 * value lazily on first read. The platform's existing Simple-only
 * signing flow keeps working unchanged when the column is null.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('signature_grade_required', 16)
                ->nullable()
                ->after('signatures');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('signature_grade_required');
        });
    }
};
