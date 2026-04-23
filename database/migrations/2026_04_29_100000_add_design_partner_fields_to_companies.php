<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design-partner tagging on companies.
 *
 * Ahead of a commercial launch, Trilink onboards a hand-picked cohort
 * (~10 suppliers, ~3 buyers) as "design partners" who use the platform
 * end-to-end while the product team is still iterating. Tagging them at
 * the Company level (rather than a separate table) means every existing
 * query, permission check, and relation — verification, RFQ ownership,
 * bids, payments, audit — already works. The admin dashboard just filters
 * on `is_design_partner = true`.
 *
 * `design_partner_role` lets us report separately on buyer-side vs
 * supplier-side cohorts (a company that plays both roles picks whichever
 * side is their primary engagement). `design_partner_started_at` is the
 * clock we measure time-to-first-milestone against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_design_partner')->default(false)->after('status');
            $table->string('design_partner_role', 16)->nullable()->after('is_design_partner');
            $table->timestamp('design_partner_started_at')->nullable()->after('design_partner_role');
            $table->text('design_partner_notes')->nullable()->after('design_partner_started_at');

            // Tiny set, so a plain index on the flag is enough — admins
            // always filter on is_design_partner first.
            $table->index('is_design_partner');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['is_design_partner']);
            $table->dropColumn([
                'is_design_partner',
                'design_partner_role',
                'design_partner_started_at',
                'design_partner_notes',
            ]);
        });
    }
};
