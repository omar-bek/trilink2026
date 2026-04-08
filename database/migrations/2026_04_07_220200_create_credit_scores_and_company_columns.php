<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * credit_scores — append-only audit trail of every credit score we
 * pulled for a company. Phase 2 / Sprint 10 / task 2.16.
 *
 * Bureau scores have audit value over time (a deteriorating score is a
 * leading indicator) so we never overwrite — every fetch produces a new
 * row and the latest one is denormalised onto `companies.latest_credit_*`
 * for cheap reads.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedSmallInteger('score');     // 0-1000 (AECB scale)
            $table->string('band', 16)->nullable();    // excellent / good / fair / poor
            $table->json('reasons')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('latest_credit_score')->nullable()->after('sanctions_screened_at');
            $table->string('latest_credit_band', 16)->nullable()->after('latest_credit_score');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['latest_credit_score', 'latest_credit_band']);
        });
        Schema::dropIfExists('credit_scores');
    }
};
