<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->json('parties');
            $table->json('amounts')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('AED');
            $table->json('payment_schedule')->nullable();
            $table->json('signatures')->nullable();
            $table->text('terms')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('buyer_company_id');
            $table->index('purchase_request_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('from_version');
            $table->json('changes');
            $table->string('status')->default('draft');
            $table->text('reason')->nullable();
            $table->json('approval_history')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();

            $table->index(['contract_id', 'status']);
        });

        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['contract_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contracts');
    }
};
