<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / task 1.13 — per-user search history.
 *
 * One row per executed search, scoped to the user. The application keeps
 * only the latest 10 per user (older rows are pruned in
 * SearchHistory::record()), so the table never grows unbounded.
 *
 * Indexed by (user_id, created_at desc) so the "show me my last 10
 * searches" query is a single index lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_history', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('term', 200);
            $t->unsignedInteger('result_count')->default(0);
            $t->timestamps();

            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->index(['user_id', 'created_at'], 'search_history_user_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_history');
    }
};
