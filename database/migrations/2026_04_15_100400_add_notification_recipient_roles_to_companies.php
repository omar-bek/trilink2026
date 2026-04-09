<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification recipient roles — JSON list of UserRole values that
 * should receive contract / amendment / signature notifications for
 * this company.
 *
 * Why: notification fan-out previously sent every event to EVERY
 * user of every party company. A 20-employee company would receive
 * 20 emails per contract event. Most of those people don't care.
 * The recipient_roles list lets the company manager say "only notify
 * company_manager + finance" so the rest of the team isn't spammed.
 *
 * Null = legacy behaviour (notify everyone), so existing companies
 * keep working unchanged. The company profile page lets a manager
 * narrow it down whenever they want.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('notification_recipient_roles')->nullable()->after('approval_threshold_aed');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('notification_recipient_roles');
        });
    }
};
