<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * teach the e-invoice pipeline to transmit BOTH tax invoices and tax
 * credit notes through the FTA Peppol channel.
 *
 * The Phase 5 review surfaced that we built credit notes in Phase 1
 * but the e-invoicing pipeline only handles tax_invoices. Under FTA
 * Phase 1 every legal tax document — invoice OR credit note — must
 * be cleared by FTA. Refunds and cancellations issued via the
 * platform's existing credit-note flow currently NEVER touch FTA,
 * which means input-tax adjustments at the buyer side break.
 *
 * Schema change:
 *
 *   - Add `document_type` enum (`invoice` | `credit_note`) so a single
 *     submission row can describe either kind. Existing rows backfill
 *     to 'invoice' which is what they always were.
 *
 *   - Add `tax_credit_note_id` nullable FK so credit-note submissions
 *     can be joined back to their source row. The existing
 *     `tax_invoice_id` becomes nullable for credit-note rows (which
 *     point to tax_credit_notes instead).
 *
 *   - The unique-by-(tax_invoice_id) implicit assumption goes away —
 *     a credit note row may have tax_invoice_id = null. The existing
 *     status indexes still work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_invoice_submissions', function (Blueprint $table) {
            // The document_type discriminator. Default to 'invoice' so
            // every existing row keeps its current semantics.
            $table->string('document_type', 16)
                ->default('invoice')
                ->after('asp_environment');

            // Optional FK to tax_credit_notes — populated only when
            // document_type = credit_note.
            $table->foreignId('tax_credit_note_id')
                ->nullable()
                ->after('tax_invoice_id')
                ->constrained('tax_credit_notes')
                ->cascadeOnDelete();
        });

        // Make tax_invoice_id nullable so credit-note rows don't have
        // to populate it. We can't drop a constrained FK on MySQL
        // with a single ->change() — drop the FK first, then re-add
        // it as nullable.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            // Drop existing FK + re-add nullable.
            try {
                Schema::table('e_invoice_submissions', function (Blueprint $table) {
                    $table->dropForeign(['tax_invoice_id']);
                });
            } catch (Throwable) {
                // FK name may differ across schemas — best-effort drop.
            }
            DB::statement(
                'ALTER TABLE e_invoice_submissions MODIFY COLUMN tax_invoice_id BIGINT UNSIGNED NULL'
            );
            Schema::table('e_invoice_submissions', function (Blueprint $table) {
                $table->foreign('tax_invoice_id')
                    ->references('id')->on('tax_invoices')
                    ->nullOnDelete();
            });
        } else {
            // SQLite is permissive — change() works.
            Schema::table('e_invoice_submissions', function (Blueprint $table) {
                $table->foreignId('tax_invoice_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('e_invoice_submissions', function (Blueprint $table) {
            $table->dropForeign(['tax_credit_note_id']);
            $table->dropColumn(['document_type', 'tax_credit_note_id']);
        });
    }
};
