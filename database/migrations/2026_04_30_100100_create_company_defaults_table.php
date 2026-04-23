<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            // Commercial defaults — applied when a new RFQ / PO / contract
            // is created. A procurement lead changes these once and every
            // future document is pre-populated instead of re-typed.
            $table->string('default_currency', 3)->default('AED');
            $table->string('default_language', 5)->default('en');
            $table->string('default_timezone', 64)->default('Asia/Dubai');

            // Fiscal year start (Jan=1..Dec=12). Drives the default date
            // range on spend analytics and the "FY-to-date" widget.
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);

            // VAT / tax defaults.
            $table->decimal('default_vat_rate', 5, 2)->default(5.00);
            $table->string('default_vat_treatment', 32)->default('standard');

            // Default payment terms for contracts created by this
            // company (days net). Supplier side uses this for invoice
            // due dates; buyer side uses it to propose terms on new RFQs.
            $table->unsignedSmallInteger('default_payment_terms_days')->default(30);
            $table->unsignedSmallInteger('late_payment_penalty_percent')->default(0);

            // Approval routing — any contract at or above this AED amount
            // requires an internal approver. Mirrors the legacy column on
            // companies but lives here so the defaults page can expose a
            // single approval-policy block.
            $table->unsignedBigInteger('contract_approval_threshold_aed')->nullable();
            $table->unsignedBigInteger('payment_dual_approval_threshold_aed')->nullable();

            // Procurement policy defaults.
            $table->boolean('require_three_quotes_above_threshold')->default(false);
            $table->unsignedBigInteger('three_quotes_threshold_aed')->default(10000);
            $table->boolean('prefer_local_suppliers')->default(false);
            $table->boolean('require_icv_certificate')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_defaults');
    }
};
