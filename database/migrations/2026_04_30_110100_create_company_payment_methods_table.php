<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Rail key — matches the PaymentRail enum value. Each row is a
            // tenant-level toggle: "We accept UAEFTS" / "We refuse cheques".
            $table->string('rail', 32);

            // The matrix a manager edits on the page. Off rails never show
            // up in the PaymentController's settle-form dropdown. When on,
            // the min/max/preferred_above values steer the finance team
            // without outright banning a rail.
            $table->boolean('accept_incoming')->default(true);
            $table->boolean('allow_outgoing')->default(true);

            // Threshold gates — null = not enforced. A manager can say
            // "UAEFTS preferred above 100,000 AED" and the settle form
            // badges UAEFTS as the recommended rail once the amount
            // crosses the threshold. Card rails typically max out at
            // 50,000; tune per company risk appetite.
            $table->unsignedBigInteger('min_amount_aed')->nullable();
            $table->unsignedBigInteger('max_amount_aed')->nullable();
            $table->unsignedBigInteger('preferred_above_aed')->nullable();
            $table->boolean('require_dual_approval')->default(false);

            // Optional binding to a specific receiving bank account — for
            // incoming rails only (UAEFTS / SWIFT / IPI route to a named
            // IBAN). Null lets the system use the company's default.
            $table->foreignId('receiving_account_id')->nullable()
                ->constrained('company_bank_accounts')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'rail']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_methods');
    }
};
