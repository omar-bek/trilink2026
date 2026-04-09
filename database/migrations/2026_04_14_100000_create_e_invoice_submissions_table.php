<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (UAE Compliance Roadmap) — e-Invoicing submission ledger.
 *
 * The FTA's e-Invoicing programme (Federal Tax Authority — Phase 1
 * effective 1 July 2026 for entities with revenue > AED 100M, Phase 2
 * January 2027, Phase 3 July 2027) requires every B2B and B2G tax
 * invoice issued in the UAE to be transmitted via the Peppol 5-corner
 * model:
 *
 *   1. Issuer (us)               → 2. Sender ASP (Accredited Service Provider)
 *   2. Sender ASP                → 3. FTA validation hub (real-time clearance)
 *   3. FTA validation hub        → 4. Receiver ASP
 *   4. Receiver ASP              → 5. Recipient (the buyer's accounting system)
 *
 * The clearance stamp the FTA returns is what makes the invoice
 * legally final — until the platform can transmit + receive that
 * stamp, no buyer paying VAT can deduct it.
 *
 * This table is the ledger of every transmission attempt. One row per
 * (tax_invoice, asp_provider) pair. Re-tries reuse the same row and
 * bump `retries`; the `next_retry_at` column is the index the queue
 * worker uses to find rows ready for another attempt.
 *
 * The platform hasn't yet picked an ASP at the time of Phase 5 — the
 * skeleton ships with a MockAspProvider that produces valid UBL 2.1
 * XML but returns a fake clearance id locally. When the real ASP is
 * picked (Avalara, Sovos, Pagero, Tradeshift, Comarch, ...), only the
 * provider class is added; everything in this table and the dispatch
 * pipeline stays unchanged.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('e_invoice_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tax_invoice_id')
                ->constrained('tax_invoices')
                ->cascadeOnDelete();

            // Provider slug. Validated against the configured provider
            // registry in PHP — adding a new provider is one entry in
            // config/einvoice.php, no migration.
            //
            // Allowed: mock | avalara | sovos | pagero | tradeshift |
            // comarch (initially), with room to grow.
            $table->string('asp_provider', 32);

            // sandbox | production. Stays sandbox during Phase 5; flips
            // to production when the real ASP credentials land.
            $table->string('asp_environment', 16)->default('sandbox');

            // Lifecycle. Allowed values:
            //   queued     — created, waiting for the queue worker
            //   submitted  — sent to ASP, awaiting clearance
            //   accepted   — FTA clearance received (the legally final state)
            //   rejected   — FTA validation failed; needs human intervention
            //   failed     — transient error during transmission, may retry
            $table->string('status', 16)->default('queued');

            // The UBL 2.1 PINT-AE XML payload. Stored inline because it
            // is small (10-50 KB per invoice) and grouping it with the
            // submission row keeps audit + retry trivially atomic. We
            // could move to S3 later — there's a SHA-256 column already
            // so future code can verify the offsite copy.
            $table->longText('payload_xml')->nullable();
            $table->string('payload_sha256', 64)->nullable();

            // Provider-side identifiers. Whatever the ASP returns from
            // its submit endpoint goes into asp_submission_id. The
            // FTA-issued clearance id (the legally meaningful stamp)
            // comes back later via the webhook and lands in
            // fta_clearance_id.
            $table->string('asp_submission_id')->nullable();
            $table->string('asp_acknowledgment_id')->nullable();
            $table->string('fta_clearance_id')->nullable();

            // Raw JSON of whatever the provider returned. Useful for
            // forensics when something goes wrong — preserve it
            // verbatim instead of parsing into typed columns we may
            // not understand yet.
            $table->json('asp_response_raw')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            // Retry bookkeeping. Bumped on every failed attempt. The
            // queue worker filters by next_retry_at <= now() so a
            // simple cron poller can drive retries without a separate
            // delayed-job system.
            $table->unsignedSmallInteger('retries')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamps();

            $table->index('tax_invoice_id');
            $table->index(['status', 'next_retry_at'], 'idx_einvoice_status_retry');
            $table->index('fta_clearance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoice_submissions');
    }
};
