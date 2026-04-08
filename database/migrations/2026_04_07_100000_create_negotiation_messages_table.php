<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negotiation room chat + counter-offers.
 *
 * Each row is a single message inside a bid's negotiation room. Messages can
 * be plain text ("kind = text") or a structured counter offer ("kind = counter_offer",
 * with the offer JSON holding amount/delivery_days/payment_terms/reason).
 *
 * The negotiation room itself is identified by a bid_id — there is no separate
 * "negotiation" table. A bid's latest counter_offer message represents the
 * "current offer"; accepting it closes the bid at that amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // 'buyer' or 'supplier' — cached so we don't re-derive it from the
            // sender's role on every render.
            $table->string('sender_side', 16);
            // 'text' or 'counter_offer'.
            $table->string('kind', 16)->default('text');
            $table->text('body')->nullable();
            // Counter-offer payload: {amount, currency, delivery_days, payment_terms, reason}
            $table->json('offer')->nullable();
            $table->timestamps();

            $table->index(['bid_id', 'created_at']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_messages');
    }
};
