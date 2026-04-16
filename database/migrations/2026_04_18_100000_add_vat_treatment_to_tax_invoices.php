<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * record the VAT treatment + tax category on every tax invoice so the
 * PDF and the e-invoice XML can both render the legally-correct
 * markings.
 *
 * Background: the Phase 1 invoice carries `total_tax` and a per-line
 * `tax_rate` but there is NO field telling downstream code WHY the
 * rate is what it is. The contract clauses (Phase 3) already pick the
 * right VAT case (standard / designated_zone_internal / reverse_charge)
 * but the resolved value never travels into the tax_invoices table
 * — so the PDF stays generic and the FTA inspector sees a 0% VAT
 * invoice with no marking.
 *
 * Cabinet Decision 52/2017 Article 59(1)(j) requires the marking on
 * the invoice itself when reverse charge applies. Without it the
 * invoice is invalid for input-tax recovery and the FTA fines are
 * AED 5,000 per missing marking.
 *
 * `vat_treatment` allowed values:
 *   standard                   — 5% standard rate
 *   reverse_charge             — recipient self-accounts (Article 48)
 *   designated_zone_internal   — out of scope (Cabinet Decision 59/2017)
 *   exempt                     — Article 46 (financial / residential)
 *   zero_rated                 — Article 45 (export / education / healthcare)
 *   out_of_scope               — neither inside nor outside the VAT system
 *
 * Backwards compatibility: existing rows get 'standard' by default.
 * The Phase 1 PaymentInvoiceObserver tested in production has only
 * ever produced standard 5% invoices, so this is correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_invoices', function (Blueprint $table) {
            $table->string('vat_treatment', 32)
                ->default('standard')
                ->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tax_invoices', function (Blueprint $table) {
            $table->dropColumn('vat_treatment');
        });
    }
};
