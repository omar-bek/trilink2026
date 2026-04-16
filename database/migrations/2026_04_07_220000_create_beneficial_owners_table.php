<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * beneficial_owners — natural persons who own or control 25% or more
 * of a company on the platform. Required disclosure for Gold tier and
 * above (Phase 2 / Sprint 8 / task 2.7) under UAE PDPL + GCC AML rules.
 *
 * Designed for the "manager fills a form" path, not for live identity
 * verification. The Phase 3 KYB upgrade adds Refinitiv-backed PEP
 * screening per row using `last_screened_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('full_name');
            $table->string('nationality', 64)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('id_type', 32)->nullable();      // passport, emirates_id, gcc_id, other
            $table->string('id_number', 64)->nullable();
            $table->date('id_expiry')->nullable();

            // Ownership
            $table->decimal('ownership_percentage', 5, 2);  // 0.00 - 100.00
            $table->string('role', 64)->nullable();          // shareholder, director, ubo, controller
            $table->boolean('is_pep')->default(false);       // self-declared politically exposed person
            $table->text('source_of_wealth')->nullable();

            // Compliance
            $table->timestamp('last_screened_at')->nullable();
            $table->string('screening_result', 16)->nullable(); // clean / hit / review
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('screening_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficial_owners');
    }
};
