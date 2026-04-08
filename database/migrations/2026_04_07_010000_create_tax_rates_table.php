<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tax_rates is the platform's source of truth for transactional VAT/tax.
     *
     * Until now the system applied a hardcoded 5% VAT on payments and a flat
     * 0% tax on contracts. This table replaces both: government users (or
     * admins) maintain the rate(s), and ContractService/PaymentService look
     * them up at create time. Optional category/country scoping lets the
     * platform support different rates per sector or jurisdiction.
     */
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->decimal('rate', 5, 2);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
