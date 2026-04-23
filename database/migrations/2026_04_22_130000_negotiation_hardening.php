<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negotiation hardening — Phase A of the negotiation governance pass.
 *
 * Adds the fields required to run a defensible, UAE-compliant counter-offer
 * process on top of the existing negotiation_messages table:
 *
 *   - expires_at             — per-round TTL so open counters don't linger
 *                              indefinitely; computed honouring UAE
 *                              weekends + public holidays.
 *   - expired_at             — stamped when the round transitions to
 *                              auto-rejected after expiry.
 *   - responded_at           — when the round actually moved from OPEN to
 *                              accepted / rejected / countered (distinct
 *                              from created_at, which is when the offer
 *                              was posted).
 *   - responded_by           — user who acted on the round.
 *   - tax snapshot on offer  — subtotal / vat / total locked onto the
 *                              offer JSON is not enough; we also persist
 *                              the discrete columns for indexing + fast
 *                              reporting (total spend per supplier).
 *   - signed acceptance      — when an offer is accepted, the acting user
 *                              types their name as a wet-ink equivalent;
 *                              we keep the typed signature, IP, UA, and
 *                              a sha256 hash of the accepted payload so
 *                              the acceptance is forensically provable.
 *
 * Also augments `bids` with:
 *   - negotiation_round_cap  — RFQ-owner-configurable max rounds for this
 *                              particular bid (defaults to 5 if null).
 *
 * Written defensively: every alteration guarded so a partial run on a dev
 * DB can be re-applied without manual cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('negotiation_messages')) {
            Schema::table('negotiation_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('negotiation_messages', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('round_status');
                }
                if (! Schema::hasColumn('negotiation_messages', 'expired_at')) {
                    $table->timestamp('expired_at')->nullable()->after('expires_at');
                }
                if (! Schema::hasColumn('negotiation_messages', 'responded_at')) {
                    $table->timestamp('responded_at')->nullable()->after('expired_at');
                }
                if (! Schema::hasColumn('negotiation_messages', 'responded_by')) {
                    $table->foreignId('responded_by')
                        ->nullable()
                        ->after('responded_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('negotiation_messages', 'subtotal_excl_tax')) {
                    $table->decimal('subtotal_excl_tax', 18, 2)->nullable()->after('responded_by');
                }
                if (! Schema::hasColumn('negotiation_messages', 'tax_amount')) {
                    $table->decimal('tax_amount', 18, 2)->nullable()->after('subtotal_excl_tax');
                }
                if (! Schema::hasColumn('negotiation_messages', 'total_incl_tax')) {
                    $table->decimal('total_incl_tax', 18, 2)->nullable()->after('tax_amount');
                }
                if (! Schema::hasColumn('negotiation_messages', 'signed_by_name')) {
                    $table->string('signed_by_name', 150)->nullable()->after('total_incl_tax');
                }
                if (! Schema::hasColumn('negotiation_messages', 'signed_at')) {
                    $table->timestamp('signed_at')->nullable()->after('signed_by_name');
                }
                if (! Schema::hasColumn('negotiation_messages', 'signature_ip')) {
                    $table->string('signature_ip', 45)->nullable()->after('signed_at');
                }
                if (! Schema::hasColumn('negotiation_messages', 'signature_hash')) {
                    $table->string('signature_hash', 64)->nullable()->after('signature_ip');
                }
            });
        }

        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table) {
                if (! Schema::hasColumn('bids', 'negotiation_round_cap')) {
                    $table->unsignedTinyInteger('negotiation_round_cap')->nullable()->after('notes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('negotiation_messages')) {
            Schema::table('negotiation_messages', function (Blueprint $table) {
                foreach (['signature_hash', 'signature_ip', 'signed_at', 'signed_by_name',
                    'total_incl_tax', 'tax_amount', 'subtotal_excl_tax',
                    'responded_by', 'responded_at', 'expired_at', 'expires_at'] as $col) {
                    if (Schema::hasColumn('negotiation_messages', $col)) {
                        if ($col === 'responded_by') {
                            $table->dropConstrainedForeignId($col);
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('bids') && Schema::hasColumn('bids', 'negotiation_round_cap')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->dropColumn('negotiation_round_cap');
            });
        }
    }
};
