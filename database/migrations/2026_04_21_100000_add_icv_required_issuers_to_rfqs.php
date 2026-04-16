<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * issuer-specific ICV scoring.
 *
 * The Phase 4 review surfaced that ADNOC ICV ≠ MoIAT ICV ≠ Mubadala
 * ICV. Each major government-related buyer accepts ONLY their own
 * issuer's certificates: ADNOC tenders only honour ADNOC ICV scores,
 * Mubadala tenders only honour Mubadala scores, and the platform was
 * treating all issuers as interchangeable. A supplier with a 70%
 * MoIAT score would appear to qualify for an ADNOC tender they
 * actually can't enter.
 *
 * The fix is a JSON array on the RFQ listing the acceptable issuers.
 * When empty (the default), any verified issuer counts — that's the
 * legacy behaviour and matches every existing RFQ.
 *
 * Backwards compatibility: every existing RFQ gets `null`, the
 * scoring service treats null as "no restriction".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->json('icv_required_issuers')
                ->nullable()
                ->after('icv_minimum_score');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('icv_required_issuers');
        });
    }
};
