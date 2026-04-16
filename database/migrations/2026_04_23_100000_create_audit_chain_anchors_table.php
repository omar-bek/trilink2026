<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (UAE Compliance Roadmap) — persistent record of every
 * audit chain anchor written by `php artisan audit:anchor-chain`.
 *
 * Each row is a Merkle root (i.e. the chain head hash at a point in
 * time) + proof that it was written to external WORM storage (S3
 * Object Lock or OpenTimestamps). If the audit_logs table is tampered
 * with, comparing the current chain head against the anchored value
 * exposes the discrepancy instantly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_chain_anchors', function (Blueprint $table) {
            $table->id();
            $table->string('chain_head_hash', 64);
            $table->unsignedBigInteger('chain_head_id');
            $table->unsignedBigInteger('row_count');
            $table->string('anchor_sha256', 64);
            $table->string('storage_path')->nullable();
            $table->string('s3_etag')->nullable();
            $table->string('opentimestamps_proof')->nullable();
            $table->timestamp('anchored_at');
            $table->timestamp('created_at')->nullable();

            $table->index('anchored_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_anchors');
    }
};
