<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-amendment discussion thread.
 *
 * The contract amendment system already lets either party PROPOSE,
 * APPROVE or REJECT a clause change, but the back-and-forth talk
 * around *why* a clause should be reworded was happening on email or
 * not at all. This table gives every amendment its own thread of
 * messages so the two parties can negotiate the wording in-app and
 * the audit log carries the full conversation alongside the legal
 * decision.
 *
 * Each message belongs to a {contract_amendment} and carries the
 * sender's user + company id (not just user id) so the view can
 * group bubbles by side (buyer vs supplier) without re-resolving
 * the user → company link on every render.
 *
 * Messages are append-only — there is no update / delete endpoint.
 * Editing a posted message would muddy the audit trail; if the
 * sender misspoke they post a follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_amendment_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_amendment_id')
                ->constrained('contract_amendments')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // company_id is denormalised for fast bubble-side rendering.
            // Stored as plain unsigned big int (no FK) so soft-deleting a
            // company doesn't break the message history.
            $table->unsignedBigInteger('company_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['contract_amendment_id', 'created_at'], 'amendment_messages_thread_idx');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendment_messages');
    }
};
