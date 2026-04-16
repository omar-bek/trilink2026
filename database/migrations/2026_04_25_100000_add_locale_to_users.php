<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint — notification i18n.
 *
 * Persists the user's preferred locale on the user row so queued
 * notifications (which run outside any HTTP request) can be rendered
 * in the right language. Without this, every queued mail would render
 * in `config('app.locale')` and Arabic-speaking users would receive
 * English emails.
 *
 * The SetLocale middleware writes to this column whenever the user
 * switches language, and User implements HasLocalePreference so
 * Laravel automatically applies it before dispatching notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 5 chars covers en, ar, en_US, ar_AE — future-proofs
            // for region-specific tweaks without another migration.
            $table->string('locale', 5)->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
