<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swift_gpi_status_events', function (Blueprint $table) {
            $table->id();

            // Every gpi-tracked payment has a UETR (Unique End-to-end
            // Transaction Reference). The column already exists on the
            // payments table — this FK is nullable because we may ingest
            // webhook updates before the outbound payment is persisted.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->char('uetr', 36)->index();

            // Status taxonomy as defined by SWIFT gpi Tracker (pacs.028):
            //  ACSP — accepted settlement in progress
            //  ACSC — accepted settlement completed
            //  ACCC — accepted settlement credited (funds at beneficiary)
            //  RJCT — rejected
            //  ACFC / ACWP / etc. — other accepted sub-states
            $table->string('status', 8);
            $table->string('status_reason', 64)->nullable();

            $table->string('from_bic', 11);
            $table->string('to_bic', 11)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->decimal('charges_amount', 14, 2)->nullable();
            $table->string('charges_currency', 3)->nullable();
            $table->string('fx_rate', 32)->nullable();

            // Timestamps as reported by the originator bank — they are
            // the source of truth for the UI's "in flight X hours" label.
            $table->timestamp('originator_time');
            $table->timestamp('received_at')->useCurrent();

            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swift_gpi_status_events');
    }
};
