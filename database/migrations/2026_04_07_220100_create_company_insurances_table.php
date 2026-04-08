<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * company_insurances — Phase 2 / Sprint 10 / task 2.13.
 *
 * Records every insurance policy a company has uploaded for verification.
 * Used by:
 *   - The verification queue (a Gold-tier promotion requires at least one
 *     active, admin-verified insurance policy of any type).
 *   - The supplier profile (Insured badge + per-policy detail card).
 *   - The contract bid pipeline (Phase 3+ trade-finance gating).
 *
 * Insurance type is a free-form string today; we'll lock it down to an
 * enum once we have a stable list of accepted types per market. Common
 * values seen so far:
 *
 *   - cargo            (in-transit goods)
 *   - public_liability (general business)
 *   - professional_indemnity
 *   - workers_comp     (KSA mandatory)
 *   - product_liability
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('type', 64);
            $table->string('insurer');
            $table->string('policy_number', 128);
            $table->decimal('coverage_amount', 15, 2);
            $table->string('currency', 3)->default('AED');

            $table->date('starts_at');
            $table->date('expires_at');

            // Document file
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();

            // Verification audit
            $table->string('status', 32)->default('pending'); // pending / verified / rejected / expired
            $table->text('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_insurances');
    }
};
