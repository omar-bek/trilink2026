<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('target_role')->nullable();
            $table->json('target_company_ids')->nullable();
            $table->string('status')->default('draft');
            $table->json('items');
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency', 3)->default('AED');
            $table->timestamp('deadline')->nullable();
            $table->text('delivery_location')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index('purchase_request_id');
            $table->index('type');
            $table->index('target_role');
            $table->index('deadline');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfqs');
    }
};
