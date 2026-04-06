<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('AED');
            $table->unsignedInteger('delivery_time_days')->nullable();
            $table->text('payment_terms')->nullable();
            $table->json('payment_schedule')->nullable();
            $table->json('items')->nullable();
            $table->timestamp('validity_date')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->json('attachments')->nullable();
            $table->json('ai_score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rfq_id', 'company_id']);
            $table->index(['rfq_id', 'status']);
            $table->index('company_id');
            $table->index('provider_id');
            $table->index('validity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
