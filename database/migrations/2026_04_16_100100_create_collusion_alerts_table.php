<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (UAE Compliance Roadmap) — anti-collusion alert queue.
 *
 * Every finding from AntiCollusionService::analyzeRfq() is persisted
 * here so the admin queue can triage asynchronously. One row per
 * (rfq, pattern) finding. The admin can label each as:
 *
 *   open          — not yet reviewed
 *   investigating — admin is looking into it
 *   false_positive— dismissed with reason (co-working space, etc.)
 *   confirmed     — action taken (bid rejected, supplier warned)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('collusion_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->string('type', 32);        // shared_ip | shared_beneficial_owner | ...
            $table->string('severity', 16);    // critical | high | medium
            $table->json('evidence');
            $table->string('status', 32)->default('open');
            $table->text('admin_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['rfq_id', 'status']);
            $table->index('status');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collusion_alerts');
    }
};
