<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Tax Invoice infrastructure: sequence allocator.
 *
 * The FTA's tax invoice rules (Federal Decree-Law 8/2017 + Cabinet Decision
 * 52/2017) require a "unique sequential number that uniquely identifies the
 * invoice". The sequence is per-issuer and per-series — INV-2026-000001
 * is the first standard invoice issued by company X in 2026, CN-2026-000001
 * is the first credit note. Each (company, series, year) tuple has its own
 * counter so resetting by year keeps numbers compact.
 *
 * The reason this lives in a dedicated table — instead of e.g. a SUBSTR on
 * tax_invoices.id — is to make atomic allocation under concurrency cheap.
 * The InvoiceNumberAllocator does:
 *
 *   BEGIN;
 *   SELECT next_value FROM invoice_number_sequences
 *     WHERE company_id = ? AND series = ? AND year = ?
 *     FOR UPDATE;
 *   UPDATE … SET next_value = next_value + 1;
 *   COMMIT;
 *
 * Two parallel tax-invoice issuances on the same company/series/year
 * serialize on the row lock instead of fighting on a unique index. The
 * lock is held for microseconds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // INV (standard tax invoice), CN (tax credit note), and any
            // future variants like SI (simplified) all share this table.
            // 8 chars is more than enough.
            $table->string('series', 8)->default('INV');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'series', 'year'], 'uniq_company_series_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
