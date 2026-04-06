<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('category')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('entity');
            $table->timestamps();

            $table->index('category');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
