<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint D.17 — per-user notification preferences.
 *
 * Stored as a single JSON column instead of a separate table because:
 *   - Preferences are read-only on every page (sidebar bell), and a join
 *     would force a per-request query against the wrong shape (we never
 *     query "all users with email-on for type X" — we ask "what does THIS
 *     user want?", which is a single-row read).
 *   - The shape is open: as we add new notification types we don't need
 *     a migration to add a column for each one — the row just grows a
 *     new key.
 *
 * Shape (all keys optional, sensible defaults applied in code):
 *   {
 *     "channels":  { "database": true,  "mail": true },
 *     "types":     { "bid_received": ["database","mail"], "contract_signed": ["database"], ... },
 *     "digest":    { "mode": "realtime|daily|off" }
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable so existing rows don't need backfill — code falls
            // back to the default channel set when the column is null.
            $table->json('notification_preferences')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
