<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction Awareness.
 *
 * The UAE business landscape has THREE legal systems running in
 * parallel: federal civil law (mainland + most free zones), DIFC
 * common law, and ADGM common law. A contract between two DIFC
 * companies that is drafted under federal civil clauses is legally
 * weak in DIFC Courts — that's the gap this phase closes.
 *
 * It also captures the VAT-relevant flags. Cabinet Decision 59/2017
 * defines a list of "Designated Zones" where goods supplied between
 * two designated zones are treated as outside the scope of UAE VAT
 * (effectively 0%). Without is_designated_zone we can't apply that
 * rule, and the platform was previously charging the standard 5% on
 * every supply by default — wrong for DAFZA-DAFZA, KIZAD-KIZAD, etc.
 *
 * Backwards compatibility: every existing company gets is_free_zone =
 * false and legal_jurisdiction = 'federal' on migration up. The
 * application code falls back to those values whenever the columns
 * are null, so unmigrated reads remain safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_free_zone')
                ->default(false)
                ->after('country');

            // Free zone authority slug. Nullable when is_free_zone is
            // false. Validated against \App\Enums\FreeZoneAuthority on
            // the application side, NOT as a DB enum, so we can append
            // new zones without a migration when MoIAT publishes them.
            $table->string('free_zone_authority', 32)
                ->nullable()
                ->after('is_free_zone');

            // VAT Designated Zone (Cabinet Decision 59/2017). True
            // when the free zone is on the FTA's designated list.
            // Drives the VAT clause selection in ContractService.
            $table->boolean('is_designated_zone')
                ->default(false)
                ->after('free_zone_authority');

            // The legal system that governs contracts the company is
            // a party to. Defaults to federal — DIFC and ADGM are the
            // only two values that change behaviour in
            // ContractService.
            $table->string('legal_jurisdiction', 16)
                ->default('federal')
                ->after('is_designated_zone');

            $table->index('is_free_zone', 'idx_companies_free_zone');
            $table->index('legal_jurisdiction', 'idx_companies_jurisdiction');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_free_zone');
            $table->dropIndex('idx_companies_jurisdiction');
            $table->dropColumn([
                'is_free_zone',
                'free_zone_authority',
                'is_designated_zone',
                'legal_jurisdiction',
            ]);
        });
    }
};
