<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (UAE Compliance Roadmap) — Tier 3 compliance certificates.
 *
 * Enterprise buyers frequently require suppliers to hold one or more
 * of the following certifications before a purchase can proceed:
 *
 *   - CoO  (Certificate of Origin) — issued by a Chamber of Commerce,
 *     proves goods originate in a specific country. Required for GCC
 *     duty exemption (0% intra-GCC tariff).
 *
 *   - ECAS (Emirates Conformity Assessment Scheme) — mandatory for
 *     regulated products (vehicles, electronics, children's products,
 *     cosmetics). Issued by ESMA.
 *
 *   - Halal — required for food, beverages, cosmetics and
 *     pharmaceuticals. Issued by EIAC or an EIAC-accredited body.
 *
 *   - GSO  (GCC Standardization Organization) — quality mark for
 *     products distributed across GCC member states.
 *
 * The table mirrors the CompanyDocument pattern (file_path + status +
 * admin verification) but is scoped per-shipment or per-product
 * rather than per-company, because the same company may ship
 * different products with different certifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_uploads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Optional linkage: some certs are per-shipment, others per-product.
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();

            // coo | ecas | halal | gso | iso | other
            $table->string('certificate_type', 32);
            $table->string('certificate_number', 128)->nullable();
            $table->string('issuer', 128)->nullable();

            $table->date('issued_date')->nullable();
            $table->date('expires_date')->nullable();

            $table->string('file_path')->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();

            $table->string('status', 16)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'certificate_type']);
            $table->index('status');
            $table->index('expires_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_uploads');
    }
};
