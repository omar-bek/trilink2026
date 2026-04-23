<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Short label the manager recognises on the payout dropdown —
            // e.g. "ENBD AED operating", "Mashreq USD payroll". Free-text.
            $table->string('label', 120);

            $table->string('holder_name', 200);
            $table->string('bank_name', 200);
            $table->string('iban', 50)->nullable();
            $table->string('swift', 20)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('currency', 3);

            // Purpose flags — a single account may serve multiple purposes
            // but a manager can dedicate one account per role (e.g. all
            // supplier payouts → ENBD AED; all payroll → WPS account).
            $table->boolean('is_default_receiving')->default(false);
            $table->boolean('is_default_payout')->default(false);
            $table->boolean('is_wps_account')->default(false);
            $table->boolean('is_tax_account')->default(false);

            $table->string('status', 32)->default('active'); // active|suspended|closed

            // Optional verification — the bank partner returns a cents
            // micro-deposit; the manager confirms and we mark verified.
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'currency']);
            $table->index(['company_id', 'is_default_receiving']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
