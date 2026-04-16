<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Tax Invoice infrastructure: invoices table.
 *
 * Schema designed against FTA "Tax Invoice" requirements
 * (Federal Decree-Law 8/2017 Article 65 + Cabinet Decision 52/2017
 * Article 59). Every column maps to a mandatory or recommended field on
 * a UAE tax invoice; nothing is decorative.
 *
 * Snapshot strategy: every party detail (name, TRN, address) is COPIED
 * into the row at issue time, not referenced via FK. A tax invoice is a
 * legal record of a moment in time — if the supplier later changes their
 * registered address, the historical invoices must keep showing the old
 * one. The same applies to line item descriptions, prices, and tax rate.
 *
 * Status lifecycle:
 *   issued (default) → may transition to voided (with reason + actor).
 *   Hard delete is forbidden — the soft delete column exists only as a
 *   defence-in-depth for accidental Eloquent ::delete() calls; the void
 *   action is the only legitimate way to invalidate an invoice and it
 *   does NOT remove the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();

            // Sequential identifier — set by InvoiceNumberAllocator at issue
            // time. Format: INV-YYYY-NNNNNN (e.g. INV-2026-000123). Unique
            // platform-wide because we prefix the series with the company
            // when more than one issuer shares the database. The unique
            // constraint protects against bugs in the allocator.
            $table->string('invoice_number', 32)->unique();

            // Source linkage. Both nullable because some manually-issued
            // invoices won't have a payment behind them yet, and a small
            // number of statement-style invoices may roll up multiple
            // payments — both edge cases are out of scope for Phase 1
            // but the columns are nullable so we don't need to migrate.
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // FTA-mandated dates: issue_date is when the invoice is created,
            // supply_date is when the underlying goods/services were supplied
            // (often equal but not always — pre-payments and milestone
            // releases shift them apart).
            $table->date('issue_date');
            $table->date('supply_date');

            // ===== Supplier (issuer) snapshot =====
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('supplier_trn', 32)->nullable();
            $table->string('supplier_name');           // snapshot
            $table->text('supplier_address')->nullable();
            // Wide enough to accept both ISO 3166-1 alpha-2 ("AE") and the
            // legacy 3-letter and free-form values ("UAE", "United Arab
            // Emirates") that exist on companies.country in production.
            $table->string('supplier_country', 8)->nullable();

            // ===== Buyer (recipient) snapshot =====
            $table->foreignId('buyer_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('buyer_trn', 32)->nullable();
            $table->string('buyer_name');              // snapshot
            $table->text('buyer_address')->nullable();
            $table->string('buyer_country', 8)->nullable();

            // ===== Line items =====
            //
            // Each row in the JSON array is one line on the printed invoice.
            // Required keys per line:
            //   description, quantity, unit, unit_price, discount,
            //   taxable_amount (qty*unit_price - discount),
            //   tax_rate, tax_amount, line_total (taxable + tax)
            //
            // We don't normalise into a child table because tax invoices
            // are immutable once issued — there's no benefit to query the
            // line items relationally. Snapshot in JSON is faster to read
            // and impossible to corrupt with cascade updates.
            $table->json('line_items');

            // ===== Totals =====
            $table->decimal('subtotal_excl_tax', 15, 2);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2);
            $table->decimal('total_inclusive', 15, 2);
            $table->string('currency', 3)->default('AED');

            // ===== Document =====
            // pdf_path is null until the IssueTaxInvoiceJob has rendered
            // the PDF and stored it on the local disk. pdf_sha256 is the
            // hash of the bytes for tamper-evidence + integrity proof.
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();

            // ===== Lifecycle =====
            $table->string('status', 16)->default('issued'); // issued | voided
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // useCurrent() emits DEFAULT CURRENT_TIMESTAMP so MySQL strict mode
            // is happy. Application code sets the value explicitly anyway.
            $table->timestamp('issued_at')->useCurrent();

            // Optional: arbitrary metadata that downstream Phase 5 (e-Invoicing)
            // will read — e.g. the e-invoice submission id once the row is
            // forwarded to the ASP.
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_company_id', 'issue_date'], 'idx_tax_inv_supplier_date');
            $table->index(['buyer_company_id', 'issue_date'], 'idx_tax_inv_buyer_date');
            $table->index('payment_id');
            $table->index('contract_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_invoices');
    }
};
