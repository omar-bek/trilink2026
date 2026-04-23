<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A — let procurement teams declare which bank guarantees a
 * supplier must attach to their bid. Stored as a JSON list of
 * objects `{type, percentage, mandatory}` so a single RFQ can demand
 * a bid bond at tender time and a performance bond at award time.
 *
 * Example:
 *   [
 *     {"type":"bid_bond","percentage":2,"mandatory":true},
 *     {"type":"performance_bond","percentage":10,"mandatory":true}
 *   ]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            if (! Schema::hasColumn('rfqs', 'required_bank_guarantees')) {
                $table->json('required_bank_guarantees')->nullable()->after('icv_required_issuers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            if (Schema::hasColumn('rfqs', 'required_bank_guarantees')) {
                $table->dropColumn('required_bank_guarantees');
            }
        });
    }
};
