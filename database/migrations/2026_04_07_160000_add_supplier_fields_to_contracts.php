<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds supplier-side contract fields:
 * - `progress_percentage`: overridable 0-100 progress bar (falls back to
 *   the status-derived default in ContractController::progressFor when null).
 * - `progress_updates`: JSON log of `{at, by, percent, note}` entries so the
 *   supplier can record production milestones without a separate table.
 * - `supplier_documents`: JSON list of `{name, path, size, uploaded_at, uploaded_by}`
 *   for production photos, quality certs, delivery receipts, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->nullable()->after('status');
            $table->json('progress_updates')->nullable()->after('progress_percentage');
            $table->json('supplier_documents')->nullable()->after('progress_updates');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'progress_updates', 'supplier_documents']);
        });
    }
};
