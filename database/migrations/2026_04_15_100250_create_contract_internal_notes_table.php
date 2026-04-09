<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal team notes for a contract — visible only to users
 * belonging to the SAME company as the author. Lets the procurement
 * team write things like "supplier price is 15% above market — push
 * back at next round" without the supplier ever seeing it.
 *
 * Strict tenant isolation is enforced at the read path:
 * ContractController::show() loads notes filtered by
 * (contract_id, company_id = current user's company) so a
 * cross-company SELECT can never accidentally leak.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')
                ->constrained('contracts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // company_id is the tenant scope key — every read MUST
            // filter on it. Stored as a plain unsigned int (not a FK)
            // so soft-deleting a company doesn't break access for the
            // remaining team members.
            $table->unsignedBigInteger('company_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['contract_id', 'company_id'], 'internal_notes_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_internal_notes');
    }
};
