<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fta_tax_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // Which tax this row belongs to — drives the filing bucket
            // and the downstream FTA portal API the submission hits.
            $table->string('tax_type', 32);            // vat|corporate_tax|excise|wht
            $table->string('filing_period', 16);       // e.g. 2026-Q1, 2026-M04, 2026
            $table->string('direction', 16);           // payable (owed to FTA) | reclaimable (credit)

            $table->decimal('amount_aed', 18, 2);
            $table->decimal('rate_percent', 5, 2)->nullable();

            // When the tax row was accrued vs physically moved to the
            // dedicated FTA account. `routed_at` is set by the auto-
            // routing job once the funds land in the company's tax
            // bank account; `remitted_at` after we successfully push to
            // the FTA portal.
            $table->timestamp('accrued_at')->useCurrent();
            $table->timestamp('routed_at')->nullable();
            $table->timestamp('remitted_at')->nullable();

            $table->foreignId('source_bank_account_id')->nullable()
                ->constrained('company_bank_accounts')->nullOnDelete();
            $table->foreignId('destination_bank_account_id')->nullable()
                ->constrained('company_bank_accounts')->nullOnDelete();

            $table->string('fta_reference', 100)->nullable();
            $table->string('status', 32)->default('accrued');  // accrued|routed|remitted|reconciled|failed

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'tax_type', 'filing_period']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fta_tax_ledger');
    }
};
