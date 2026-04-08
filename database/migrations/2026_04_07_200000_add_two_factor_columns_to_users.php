<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds TOTP 2FA columns to the users table. Shape matches Laravel Fortify's
 * so a future upgrade to the official package is a drop-in replacement:
 *
 *   two_factor_secret          — base32 secret, nullable (null = 2FA off)
 *   two_factor_recovery_codes  — JSON array of one-time recovery codes
 *   two_factor_confirmed_at    — timestamp once the user confirmed setup
 *
 * The columns are encrypted at the model level (cast to 'encrypted' on
 * User) so a DB leak still protects the secret and recovery codes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
