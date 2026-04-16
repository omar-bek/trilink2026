<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Tax Credit Notes table.
 *
 * Whenever a tax invoice is partially or fully reversed (refund, dispute
 * settlement, correction, cancellation) the FTA requires a "tax credit
 * note" to be issued — Cabinet Decision 52/2017 Article 60. The credit
 * note has a separate sequential numbering series (CN-YYYY-NNNNNN) and
 * mandates a back-reference to the original invoice number.
 *
 * Schema mirrors tax_invoices for symmetry — same snapshot pattern, same
 * line_items JSON shape, same status/audit columns. The only difference
 * is the original_invoice_id FK and the `reason` enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_credit_notes', function (Blueprint $table) {
            $table->id();

            $table->string('credit_note_number', 32)->unique();

            // The invoice this credit note rolls back. Always required —
            // free-floating credit notes don't exist in the FTA model.
            $table->foreignId('original_invoice_id')
                ->constrained('tax_invoices')
                ->cascadeOnDelete();

            $table->date('issue_date');

            // Why was this credit note issued? Drives both the printable
            // text on the document and downstream reporting (e.g. the
            // monthly VAT return needs to know how much was refunded
            // under each category).
            $table->string('reason', 32);
            // Allowed values: refund | correction | cancellation |
            //                 dispute_settlement | post_supply_discount |
            //                 goods_returned

            $table->text('notes')->nullable();

            // Same line-item shape as tax_invoices — the credit note
            // describes the same goods/services with negative monetary
            // effect. We don't store negative numbers in the JSON; the
            // PDF and reports apply the sign at render time.
            $table->json('line_items');

            $table->decimal('subtotal_excl_tax', 15, 2);
            $table->decimal('total_tax', 15, 2);
            $table->decimal('total_inclusive', 15, 2);
            $table->string('currency', 3)->default('AED');

            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // useCurrent() emits DEFAULT CURRENT_TIMESTAMP so MySQL strict mode
            // accepts the column; application code overrides it on insert.
            $table->timestamp('issued_at')->useCurrent();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('original_invoice_id');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_credit_notes');
    }
};
