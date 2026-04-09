<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (UAE Compliance Roadmap) — ICV weighting on RFQs.
 *
 * The buyer can choose to weight the bid evaluation by both price AND
 * the supplier's ICV score:
 *
 *   composite = (1 - w) × price_score + w × icv_score
 *
 * where `w` is the icv_weight_percentage divided by 100. Government
 * tenders typically use 30% (i.e. 70/30 price/ICV split).
 *
 * `icv_minimum_score` is a hard cutoff — bidders below this number
 * are flagged as disqualified in the compare-bids view (still visible
 * but ranked at the bottom). It's optional; null means no cutoff.
 *
 * Backwards compatibility: every existing RFQ gets icv_weight_percentage
 * = 0, which makes the composite formula collapse to pure price
 * scoring — exactly the behaviour the platform had before this
 * migration. No existing RFQ is changed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            // Weight as an integer 0..50. Capped at 50 because beyond
            // that the price signal becomes meaningless and we don't
            // see government buyers go higher in practice.
            $table->unsignedTinyInteger('icv_weight_percentage')
                ->default(0)
                ->after('currency');

            // Optional minimum ICV score. Null = no cutoff. Stored as
            // decimal so it can match the score column on icv_certificates.
            $table->decimal('icv_minimum_score', 5, 2)
                ->nullable()
                ->after('icv_weight_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn(['icv_weight_percentage', 'icv_minimum_score']);
        });
    }
};
