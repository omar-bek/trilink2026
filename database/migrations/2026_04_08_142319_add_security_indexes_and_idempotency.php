<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade-finance security hardening migration. Bundles three concerns that
 * all hit the same money-handling tables so they ship in one rollback unit:
 *
 *   1. escrow_releases.confirmed_at — proper idempotency token for the
 *      bank-deposit webhook. Today the service compares notes === 'Confirmed
 *      via webhook' which is fragile (a future caller writing the same
 *      string would silently break it).
 *
 *   2. webhook_events table — replay-protection store for ALL incoming
 *      webhook deliveries (PayPal, Stripe, escrow banks). We persist the
 *      provider event id and refuse to process the same id twice.
 *
 *   3. Performance indexes on hot date columns flagged by the audit:
 *      payments.approved_at, payments.created_at, contracts.created_at,
 *      contracts.progress_percentage, shipments.estimated_delivery. All
 *      five are filtered/sorted in dashboard queries and the absence of an
 *      index forces a full table scan once volume builds up.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Idempotency token for escrow webhook deposits.
        Schema::table('escrow_releases', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('recorded_at');
            $table->index('confirmed_at');
        });

        // 2. Replay-protection store for webhook deliveries. The unique
        //    (provider, event_id) index is the safety net even if the
        //    application code path forgets to check.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);            // 'stripe', 'paypal', 'escrow_mashreq', ...
            $table->string('event_id', 191);           // provider-supplied unique id
            $table->string('event_type', 100)->nullable();
            $table->json('payload')->nullable();       // raw payload for forensic replay
            $table->timestamp('processed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index('processed_at');
        });

        // 3. Performance indexes on date columns the dashboards filter on.
        Schema::table('payments', function (Blueprint $table) {
            $table->index('approved_at');
            $table->index('created_at');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('progress_percentage');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index('estimated_delivery');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['estimated_delivery']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['progress_percentage']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::dropIfExists('webhook_events');

        Schema::table('escrow_releases', function (Blueprint $table) {
            $table->dropIndex(['confirmed_at']);
            $table->dropColumn('confirmed_at');
        });
    }
};
