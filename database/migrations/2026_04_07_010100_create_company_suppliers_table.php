<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_suppliers — explicit "this supplier belongs to this company"
     * relationship.
     *
     * Used to enforce the rule: "my own supplier cannot bid on my RFQs but
     * can bid on RFQs from other companies in the same field". Existence of
     * a row here causes BidService::create() to reject the bid.
     */
    public function up(): void
    {
        Schema::create('company_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_company_id']);
            $table->index('supplier_company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_suppliers');
    }
};
