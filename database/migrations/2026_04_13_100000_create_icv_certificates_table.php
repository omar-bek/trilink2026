<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 (UAE Compliance Roadmap) — In-Country Value (ICV) certificates.
 *
 * The MoIAT-administered ICV programme assigns each UAE supplier a
 * percentage score (0-100) reflecting how much of their economic
 * activity stays in the UAE: spend with local sub-suppliers, Emiratis
 * employed, capital invested in local assets, R&D and training
 * expenditure. The certificate is issued by either MoIAT directly or
 * by one of the major government-related buyers (ADNOC, Mubadala, EGA,
 * EWEC, ETIHAD, EMSTEEL) and re-issued annually.
 *
 * Government tenders weight bids by ICV — typical evaluation formula
 * is 70% price + 30% ICV. Without an ICV record on the supplier
 * profile, the platform's bid evaluation can never produce a fair
 * composite score for a government buyer, which is the gating factor
 * for the entire public-sector segment.
 *
 * The table stores ONE row per (company, issuer, certificate_number)
 * tuple. A supplier can hold multiple active certificates from
 * different issuers (e.g. one MoIAT + one ADNOC) — the scoring
 * service picks the most recent verified active one for each
 * evaluation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('icv_certificates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            // Issuer slug (validated in PHP against an allowlist:
            // moiat | adnoc | mubadala | ega | ewec | etihad | emsteel | other).
            // Stored as a free-form string so adding a new issuer doesn't
            // need a migration when MoIAT delegates a new entity.
            $table->string('issuer', 32);

            // The certificate number as printed on the document. NOT
            // unique by itself — same number can appear across different
            // companies if they share an issuer's numbering convention.
            // Uniqueness is enforced on the (company, issuer, number)
            // tuple instead.
            $table->string('certificate_number', 64);

            // Score: 0.00–100.00, two decimals (e.g. 38.45). MoIAT
            // formats vary across issuers but all bound to this range.
            $table->decimal('score', 5, 2);

            // Lifecycle dates. Both required — every certificate has an
            // effective date and an expiry that the scoring service
            // uses to filter out stale rows.
            $table->date('issued_date');
            $table->date('expires_date');

            // Document storage — same shape as company_documents so the
            // upload UX is consistent.
            $table->string('file_path')->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();

            // Verification lifecycle. pending → verified | rejected.
            // 'expired' is set automatically by the scheduled command
            // when expires_date passes.
            $table->string('status', 16)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'issuer', 'certificate_number'], 'uniq_company_issuer_cert');
            $table->index(['company_id', 'status'], 'idx_icv_company_status');
            $table->index('expires_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icv_certificates');
    }
};
