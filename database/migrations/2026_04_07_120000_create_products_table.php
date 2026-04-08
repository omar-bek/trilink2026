<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products — supplier-listed catalog items with fixed prices.
     *
     * Until now everything was RFQ-driven (quote-on-demand). The catalog
     * unlocks an Amazon-style "Buy Now" experience for standard goods:
     * suppliers list their inventory once, buyers purchase directly without
     * the round-trip of an RFQ. Custom/large orders still go through RFQ.
     *
     * Buy-Now creates a Contract directly via ContractService::createFromProduct()
     * — the existing payment + signing + tax pipeline applies unchanged.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('hs_code', 16)->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->decimal('base_price', 15, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('unit', 32)->default('pcs');
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedInteger('stock_qty')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->json('images')->nullable();
            $table->json('specs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index('category_id');
            $table->index('hs_code');
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
