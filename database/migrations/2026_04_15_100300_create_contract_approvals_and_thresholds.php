<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval routing engine — lightweight MVP.
 *
 * Adds:
 *   1. `approval_threshold_aed` on companies. When the total contract
 *      value exceeds this number, the contract enters
 *      `pending_internal_approval` status BEFORE going to
 *      `pending_signatures`. Required approval count is implicit:
 *      one approval from a user with the `contract.approve`
 *      permission is enough.
 *   2. `contract_approvals` audit table with one row per approval
 *      action (approved or rejected) so the audit log captures
 *      WHO approved WHAT, WHEN, with optional notes. Required
 *      because the JSON-only approach used elsewhere doesn't give
 *      us per-row foreign keys for the manager-facing approvals
 *      dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Null = no threshold (every contract goes straight to
            // pending_signatures, current behaviour). Numeric value =
            // contracts ABOVE this AED amount need internal approval.
            $table->decimal('approval_threshold_aed', 15, 2)->nullable()->after('legal_jurisdiction');
        });

        Schema::create('contract_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('company_id');
            $table->string('decision', 16); // 'approved' | 'rejected'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'decision']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_approvals');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('approval_threshold_aed');
        });
    }
};
