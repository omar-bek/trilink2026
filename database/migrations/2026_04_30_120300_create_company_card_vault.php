<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_card_vault', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_by_user_id')->constrained('users')->cascadeOnDelete();

            // PCI-DSS firewall: we NEVER store PAN / CVV. Only the token
            // handed back by the gateway (Stripe, Checkout.com, Network
            // International) that lets us charge the card again without
            // touching card data ourselves.
            $table->string('gateway', 32);        // stripe|checkout|network|telr|magnati
            $table->string('token');              // opaque gateway-issued
            $table->string('fingerprint', 128)->nullable();

            // Safe-to-display metadata.
            $table->string('brand', 32)->nullable();     // visa|mastercard|amex|unionpay
            $table->char('last4', 4);
            $table->unsignedTinyInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->string('cardholder_name', 200)->nullable();
            $table->string('issuing_country', 2)->nullable();

            // A buyer company may have one or more saved cards; exactly
            // one can carry the default flag per company at a time.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_company_card')->default(true);
            $table->string('label', 120)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_default']);
            $table->unique(['gateway', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_card_vault');
    }
};
