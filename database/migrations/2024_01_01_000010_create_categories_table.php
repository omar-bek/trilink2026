<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('path')->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('path');
            $table->index('level');
        });

        Schema::create('company_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_category');
        Schema::dropIfExists('categories');
    }
};
