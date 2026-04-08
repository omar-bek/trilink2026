<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add trade-fidelity columns to bids — Phase 2.
 *
 * Until now a bid had a single `price` decimal and the contract pipeline
 * computed VAT and treated `price` as the subtotal. That doesn't match how
 * UAE/GCC B2B procurement actually works:
 *
 *   - Suppliers must declare the Incoterm (EXW, FOB, CIF, DAP, DDP, ...) so
 *     buyers can compare apples-to-apples and know who pays freight, who
 *     pays insurance, where risk transfers, who handles customs.
 *   - Suppliers must declare whether their quoted price is VAT-inclusive,
 *     VAT-exclusive, or VAT-not-applicable (with a reason — exports,
 *     designated zone, below-threshold supplier, exempt service).
 *   - Country of origin matters for GCC Common Customs Tariff (intra-GCC
 *     duty-free) and certificate-of-origin paperwork.
 *   - HS code is needed for any imported goods at customs.
 *
 * The price the supplier types stays in the existing `price` column. The
 * derived subtotal/tax/total are stored explicitly so the contract pipeline
 * doesn't have to recompute and can't drift from what the supplier saw on
 * the form.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // Trade terms — these answer "who pays for what" and "where do
            // the goods come from", which together govern the landed cost
            // the buyer is committing to.
            $table->string('incoterm', 8)->nullable()->after('payment_schedule');
            $table->string('country_of_origin', 2)->nullable()->after('incoterm');
            $table->string('hs_code', 16)->nullable()->after('country_of_origin');

            // VAT treatment — supplier-declared, not auto-inferred. The
            // legacy auto-tax behaviour stays in place when this column is
            // null (existing bids) so the migration is safe on prod data.
            //
            //   exclusive      => price is the subtotal, VAT is added on top
            //   inclusive      => price already contains VAT, subtotal is
            //                     back-calculated
            //   not_applicable => zero-rated / designated zone / below
            //                     registration threshold / exempt
            $table->string('tax_treatment', 16)->nullable()->after('hs_code');
            $table->string('tax_exemption_reason', 64)->nullable()->after('tax_treatment');

            // Snapshots — set at submit time so a tax_rates table change
            // tomorrow can't retroactively change the price the supplier
            // committed to today. Decimal(5,2) matches `tax_rates.rate`.
            $table->decimal('tax_rate_snapshot', 5, 2)->nullable()->after('tax_exemption_reason');
            $table->decimal('subtotal_excl_tax', 15, 2)->nullable()->after('tax_rate_snapshot');
            $table->decimal('tax_amount', 15, 2)->nullable()->after('subtotal_excl_tax');
            $table->decimal('total_incl_tax', 15, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn([
                'incoterm',
                'country_of_origin',
                'hs_code',
                'tax_treatment',
                'tax_exemption_reason',
                'tax_rate_snapshot',
                'subtotal_excl_tax',
                'tax_amount',
                'total_incl_tax',
            ]);
        });
    }
};
