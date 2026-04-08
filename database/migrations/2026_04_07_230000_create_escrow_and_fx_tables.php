<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Trade Finance MVP. Single migration that lays down the schema
 * the escrow + multi-currency features need:
 *
 *   - escrow_accounts:  one-per-contract account at the bank partner that
 *                       holds funds in trust until milestones release.
 *   - escrow_releases:  append-only ledger of every deposit / release /
 *                       refund event. Doubles as the audit trail required
 *                       by Sprint 13 / task 3.12.
 *   - exchange_rates:   daily currency rates synced by Sprint 14 / task
 *                       3.13. Keyed by (from, to, as_of) so we can replay
 *                       historical conversions.
 *   - contracts.escrow_account_id : nullable FK so each contract can point
 *                       at its account once activated. Nullable because
 *                       the activation flow is opt-in per contract.
 *   - payments.escrow_release_id  : nullable FK back to the release row
 *                       that satisfied this milestone. Lets the Payments
 *                       index UI show "Paid via Escrow" instead of a
 *                       generic gateway label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // Free-form key — 'mock', 'mashreq_neobiz', 'enbd_trade'. Used by
            // BankPartnerFactory to resolve the right adapter at runtime.
            $table->string('bank_partner', 50)->default('mock');
            $table->string('external_account_id', 100)->nullable();
            $table->string('currency', 3)->default('AED');
            $table->decimal('total_deposited', 15, 2)->default(0);
            $table->decimal('total_released', 15, 2)->default(0);
            // pending → active (after first deposit) → closed (when fully
            // released or refunded). Distinct from contract.status because a
            // single contract can keep escrow open after the contract is
            // marked completed (final payment retention period).
            $table->string('status', 20)->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One escrow account per contract. The activation flow asserts
            // this with firstOrCreate but the unique index is the safety net
            // in case two requests race.
            $table->unique('contract_id');
            $table->index('status');
            $table->index('bank_partner');
        });

        Schema::create('escrow_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_account_id')->constrained()->cascadeOnDelete();
            // Optional link to the Payment row this release covers. Auto
            // releases that satisfy a payment milestone backfill this so the
            // payments index can render "Paid via escrow" + a click-through
            // to the release event. Manual ad-hoc releases leave it null.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            // 'deposit' adds to balance; 'release' subtracts (paid to
            // supplier); 'refund' subtracts (paid back to buyer).
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('AED');
            // Free-form milestone key from payment_schedule (advance,
            // production, delivery, final, ad-hoc). Lets the dashboard
            // group releases by milestone for the supplier.
            $table->string('milestone', 100)->nullable();
            // 'manual' = released by buyer click; 'auto_signature' =
            // contract sign listener; 'auto_delivery' = shipment delivered
            // listener; 'auto_inspection' = inspection pass listener;
            // 'webhook' = bank-initiated (rare); 'cron' = the every-10-min
            // sweeper that catches conditions missed by their listeners.
            $table->string('triggered_by', 30)->default('manual');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bank_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['escrow_account_id', 'type']);
            $table->index('payment_id');
            $table->index('triggered_by');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            // 8-decimal precision matches the format Open Exchange Rates
            // returns and avoids drift on multi-step conversions.
            $table->decimal('rate', 15, 8);
            $table->date('as_of');
            // 'openexchangerates' (default), 'manual', 'mock'. Lets the
            // admin UI tag the source so analysts can audit a converted
            // contract back to the rate that drove it.
            $table->string('source', 50)->default('openexchangerates');
            $table->timestamps();

            // One row per pair per day, regardless of how many times the
            // sync command runs. Reseeding the same day overwrites via
            // updateOrCreate.
            $table->unique(['from_currency', 'to_currency', 'as_of']);
            $table->index(['from_currency', 'to_currency']);
            $table->index('as_of');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('escrow_account_id')
                ->nullable()
                ->after('payment_schedule')
                ->constrained('escrow_accounts')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('escrow_release_id')
                ->nullable()
                ->after('gateway_order_id')
                ->constrained('escrow_releases')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escrow_release_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escrow_account_id');
        });

        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('escrow_releases');
        Schema::dropIfExists('escrow_accounts');
    }
};
