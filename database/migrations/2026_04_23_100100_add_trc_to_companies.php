<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (UAE Compliance Roadmap) — Tax Residency Certificate (TRC)
 * tracking. Cross-border zero-rated supplies require the buyer to
 * hold a valid TRC from their home jurisdiction. The platform stores
 * the file path + expiry so ContractService can surface a warning
 * when a cross-border contract is being signed without a valid TRC.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('trc_path')->nullable()->after('corporate_tax_registered_at');
            $table->date('trc_expires_at')->nullable()->after('trc_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['trc_path', 'trc_expires_at']);
        });
    }
};
