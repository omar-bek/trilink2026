<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users');
            $table->foreignId('against_company_id')->constrained('companies');
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('description');
            $table->boolean('escalated_to_government')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_date')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contract_id', 'status']);
            $table->index('company_id');
            $table->index('raised_by');
            $table->index('escalated_to_government');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
