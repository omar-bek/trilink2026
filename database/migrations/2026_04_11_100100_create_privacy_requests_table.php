<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (UAE Compliance Roadmap) — PDPL data-subject request queue.
 *
 * Federal Decree-Law 45/2021 grants every data subject four rights that
 * the controller (us) must operationalise within 30 days of the request:
 *
 *   - Article 13 — Right of access (data export / DSAR)
 *   - Article 14 — Right of rectification (correct wrong data)
 *   - Article 15 — Right of erasure (right to be forgotten)
 *   - Article 16 — Right of restriction (pause processing)
 *
 * This table is the queue + audit ledger for those requests. Each row
 * represents a single ask; the user can have multiple in flight (e.g.
 * an export AND an erasure scheduled for after they've downloaded
 * their data). The 30-day clock is the `scheduled_for` column for
 * erasure (cooling period during which the user can cancel) and is
 * the SLA deadline for export/rectification.
 *
 * Audit-grade: rows are NEVER hard-deleted. Withdrawn requests stay
 * in the table with `status = withdrawn` so we can prove to the UAE
 * Data Office that we honoured the right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What the data subject is asking for. Allowed values:
            //   data_export   — Article 13 (DSAR)
            //   erasure       — Article 15 (right to be forgotten)
            //   rectification — Article 14 (correct wrong data)
            //   restriction   — Article 16 (pause processing)
            $table->string('request_type', 32);

            // Lifecycle. Allowed values:
            //   pending     — submitted, not yet picked up
            //   in_review   — admin is investigating (e.g. blockers like
            //                 active contracts that prevent erasure)
            //   approved    — admin gave the green light, queue picks it up
            //   rejected    — admin denied (with reason)
            //   completed   — fulfilled, evidence in fulfillment_metadata
            //   withdrawn   — user cancelled before completion
            $table->string('status', 32)->default('pending');

            $table->timestamp('requested_at');

            // For erasure: the actual erasure date = requested_at + 30
            // days. Gives the user a cooling-off window to change their
            // mind. For data_export: the SLA deadline (also requested_at
            // + 30 days but we aim to fulfil within minutes via the job).
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Why was the request rejected? Visible to the user.
            $table->text('rejection_reason')->nullable();

            // Free-form bag for fulfilment evidence — file path of the
            // exported archive, list of tables that were anonymised,
            // count of rows touched. Used by admins reviewing the
            // request and by audit replay.
            $table->json('fulfillment_metadata')->nullable();

            // The admin who approved/rejected/handled the request.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_privacy_user_status');
            $table->index('scheduled_for');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
