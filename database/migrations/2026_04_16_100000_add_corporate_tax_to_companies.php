<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (UAE Compliance Roadmap) — Corporate Tax registration tracking.
 *
 * Federal Decree-Law 47/2022 introduced a 9% Corporate Tax on
 * profits above AED 375,000, effective 1 June 2023. Every UAE
 * company (mainland + free zone) must register for CT and receive
 * a CT registration number (separate from the VAT TRN, typically
 * in the format 100-XXX-XXX-XXX).
 *
 * The platform needs to:
 *   1. Track whether each company is CT-registered, exempt (below
 *      threshold), or a Qualifying Free Zone Person (QFZP — 0% CT
 *      on qualifying income).
 *   2. Annotate tax invoices issued by QFZP suppliers so the buyer
 *      knows the supply carries 0% CT treatment on the supplier's
 *      books — relevant for transfer pricing documentation.
 *   3. Surface the CT status in admin company profiles so the
 *      verification queue can flag companies that should be
 *      registered but aren't.
 *
 * Backwards compatibility: every existing company gets
 * corporate_tax_status = 'unknown' — the admin queue surfaces them
 * for review.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('corporate_tax_number', 32)
                ->nullable()
                ->after('tax_number');

            // Allowed values (enforced in PHP):
            //   registered             — CT number on file, standard 9% applies
            //   exempt_below_threshold — annual profits < AED 375K, CT = 0%
            //   qfzp                   — Qualifying Free Zone Person, CT = 0% on qualifying income
            //   not_registered         — should be registered but isn't (admin flag)
            //   unknown                — new company, not yet reviewed
            $table->string('corporate_tax_status', 32)
                ->default('unknown')
                ->after('corporate_tax_number');

            $table->date('corporate_tax_registered_at')
                ->nullable()
                ->after('corporate_tax_status');

            $table->index('corporate_tax_status', 'idx_companies_ct_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_ct_status');
            $table->dropColumn([
                'corporate_tax_number',
                'corporate_tax_status',
                'corporate_tax_registered_at',
            ]);
        });
    }
};
