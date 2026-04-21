<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elevates the dispute system from a single-shot grievance form into a
 * structured B2B case-management workflow. The additive columns on the
 * disputes table capture the claim (amount + remedy + severity) and the
 * richer lifecycle timestamps. Three new tables carry the interactive
 * layer a real mediation needs:
 *
 *  - dispute_messages  : threaded dialogue between parties + mediator
 *  - dispute_offers    : structured settlement offers / counter-offers
 *  - dispute_events    : append-only audit timeline of every action
 *
 * Nothing existing is renamed or dropped. Old rows remain valid; new
 * columns default to null / sensible values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            // Structured claim — a dispute without a number and a remedy
            // cannot be resolved, only argued. These turn the grievance
            // into an actionable case.
            $table->decimal('claim_amount', 15, 2)->nullable()->after('description');
            $table->string('claim_currency', 3)->default('AED')->after('claim_amount');
            $table->string('requested_remedy', 32)->nullable()->after('claim_currency');

            // Explicit severity override. The controller previously derived
            // priority from type + escalation flag; that's still the default
            // but now a mediator or claimant can set it directly.
            $table->string('severity', 16)->default('medium')->after('requested_remedy');

            // Two-tier SLA: response_due_at is how long the respondent has
            // to acknowledge/answer; the existing sla_due_date becomes the
            // final-resolution deadline. Stages each get their own clock.
            $table->timestamp('response_due_at')->nullable()->after('sla_due_date');

            // Lifecycle timestamps.
            $table->timestamp('acknowledged_at')->nullable()->after('response_due_at');
            $table->timestamp('mediation_started_at')->nullable()->after('acknowledged_at');
            $table->timestamp('withdrawn_at')->nullable()->after('mediation_started_at');

            // Adjudicated outcome — replaces the free-text `resolution` as
            // the primary resolution record. `resolution` stays as the
            // human rationale; these three are the machine-readable result.
            $table->string('decision_outcome', 24)->nullable()->after('resolution');
            $table->decimal('decision_amount', 15, 2)->nullable()->after('decision_outcome');
            $table->foreignId('decided_by')->nullable()->after('decision_amount')
                ->constrained('users')->nullOnDelete();

            $table->index('severity');
            $table->index('response_due_at');
            $table->index('decision_outcome');
        });

        // ──────────────────────────────────────────────────────────────
        // Threaded conversation. Every message carries the author's
        // company so the UI can correctly attribute it to claimant
        // vs respondent even after personnel changes. `is_internal`
        // marks notes visible only to the author's company + mediators
        // (used for internal legal strategy). `is_system` marks
        // auto-generated announcements (status changes, offers filed).
        // ──────────────────────────────────────────────────────────────
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_system')->default(false);
            $table->json('read_by')->nullable(); // array of user_ids who've read
            $table->timestamps();

            $table->index(['dispute_id', 'created_at']);
            $table->index('company_id');
        });

        // ──────────────────────────────────────────────────────────────
        // Settlement offers. Each offer is immutable once submitted;
        // a counter-offer is a new row that references the previous
        // via parent_offer_id. Offers carry their own expiry so the
        // other party can't sit on them indefinitely.
        // ──────────────────────────────────────────────────────────────
        Schema::create('dispute_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_offer_id')->nullable()->constrained('dispute_offers')->nullOnDelete();
            $table->foreignId('offered_by_user_id')->constrained('users');
            $table->foreignId('offered_by_company_id')->constrained('companies');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('remedy', 32)->nullable();
            $table->text('terms');
            $table->string('status', 16)->default('pending'); // pending|accepted|rejected|countered|expired|withdrawn
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_note')->nullable();
            $table->timestamps();

            $table->index(['dispute_id', 'status']);
            $table->index('expires_at');
        });

        // ──────────────────────────────────────────────────────────────
        // Append-only timeline. One row per action so the case file is
        // reproducible. `event` is a stable string identifier; `metadata`
        // is JSON for event-specific payload (e.g. offer_id, old_status).
        // ──────────────────────────────────────────────────────────────
        Schema::create('dispute_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('event', 48); // opened|acknowledged|message|offer_submitted|offer_accepted|...
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dispute_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_events');
        Schema::dropIfExists('dispute_offers');
        Schema::dropIfExists('dispute_messages');

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn([
                'claim_amount', 'claim_currency', 'requested_remedy', 'severity',
                'response_due_at', 'acknowledged_at', 'mediation_started_at', 'withdrawn_at',
                'decision_outcome', 'decision_amount',
            ]);
        });
    }
};
