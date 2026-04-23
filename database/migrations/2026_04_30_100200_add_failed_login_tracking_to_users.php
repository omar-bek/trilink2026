<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Login hardening counters. Updated by the AuthController on
            // every failed / successful attempt; consumed by the login
            // flow to honour the company security policy's lockout window
            // without needing a separate throttle cache key.
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->string('last_login_ip', 45)->nullable()->after('locked_until');
            $table->timestamp('session_started_at')->nullable()->after('last_login_ip');

            // Previous passwords hashes (JSON array of recent hashes, most
            // recent first). Consulted when the company policy's history
            // depth > 0 to reject password reuse.
            $table->json('password_history')->nullable()->after('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'last_login_ip',
                'session_started_at',
                'password_history',
            ]);
        });
    }
};
