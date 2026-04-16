<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * track which expiry-reminder thresholds have already been notified
 * for each ICV certificate.
 *
 * The 60/30/7 day reminder schedule is the MoIAT recommended cycle:
 * 60 days out gives the supplier enough time to start the renewal
 * paperwork; 30 days is the standard "act now" reminder; 7 days is
 * the last warning before the cert silently drops out of bid scoring.
 *
 * `last_expiry_reminder_threshold` records the FURTHEST threshold
 * we've already sent for the current cert (60, 30, or 7). The daily
 * command compares the cert's days_until_expiry against the
 * thresholds, sends the next-due notification, and bumps the column.
 * Setting it to 0 (or null) means "never notified yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icv_certificates', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_expiry_reminder_threshold')
                ->nullable()
                ->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('icv_certificates', function (Blueprint $table) {
            $table->dropColumn('last_expiry_reminder_threshold');
        });
    }
};
