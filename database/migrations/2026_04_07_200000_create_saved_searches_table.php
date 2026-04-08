<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / task 1.5 — saved searches per user.
 *
 * Each row captures one named search the user has stashed: the resource
 * type they were browsing (rfqs, suppliers, products), a label, and the
 * full filters payload as JSON. The daily digest job
 * (`SendSavedSearchDigests`, task 1.6) walks every saved search whose
 * owner has digests enabled, runs the corresponding query, and emails
 * any matching results above their match threshold (task 1.7).
 *
 * Why one table for all resource types instead of one per resource?
 *   - The shape is identical (label + filter blob + owner + match settings).
 *   - One table = one place to add features (notify_frequency, last_run_at, ...).
 *   - The digest job stays a single cron entry instead of three.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('label', 200);

            // resource_type: 'rfqs' | 'suppliers' | 'products'. Kept as a
            // string (not enum) so adding a new resource later is just a
            // controller change, no migration needed.
            $t->string('resource_type', 32)->index();

            // The full filter blob — query string params keyed by name. The
            // controller hands this back to the same index method so the
            // saved search renders identically to the live page.
            $t->json('filters');

            // Per-search digest preferences. `is_active` gates the digest
            // job; `last_notified_at` is set after a digest fires so we can
            // throttle and dedupe.
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_notified_at')->nullable();

            $t->timestamps();

            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->index(['user_id', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
