<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds structured negotiation rounds on top of the existing
     * negotiation_messages free-text room.
     *
     * `round_number` is set when the message is a counter_offer — text
     * messages remain unstructured chat. Each counter_offer increments
     * the round counter so a buyer/supplier can navigate "round 1 → round
     * 2 → round 3" cleanly in the UI and the analytics layer can ask
     * "average rounds per closed bid".
     *
     * `round_status` tracks the per-round verdict so the contract pipeline
     * knows which counter the parties actually settled on.
     */
    public function up(): void
    {
        Schema::table('negotiation_messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_number')->nullable()->after('kind');
            $table->string('round_status', 16)->default('open')->after('round_number');
            $table->index(['bid_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::table('negotiation_messages', function (Blueprint $table) {
            $table->dropIndex(['bid_id', 'round_number']);
            $table->dropColumn(['round_number', 'round_status']);
        });
    }
};
