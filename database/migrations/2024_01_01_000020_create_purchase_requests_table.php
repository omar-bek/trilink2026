<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->json('items');
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency', 3)->default('AED');
            $table->text('delivery_location')->nullable();
            $table->date('required_date')->nullable();
            $table->json('approval_history')->nullable();
            $table->boolean('rfq_generated')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index('buyer_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
