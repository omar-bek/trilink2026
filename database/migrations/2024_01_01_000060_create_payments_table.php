<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_company_id')->constrained('companies');
            $table->foreignId('buyer_id')->constrained('users');
            $table->string('status')->default('pending_approval');
            $table->decimal('amount', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(5.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('milestone')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contract_id', 'status']);
            $table->index('company_id');
            $table->index('recipient_company_id');
            $table->index('buyer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
