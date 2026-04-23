<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_security_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            // 2FA mandate — when true the login flow redirects any team
            // member without two_factor_confirmed_at to /two-factor/setup
            // and refuses to let them reach the dashboard until enrolled.
            $table->boolean('enforce_two_factor')->default(false);
            $table->unsignedSmallInteger('two_factor_grace_days')->default(7);

            // Password policy. Applied by the PasswordPolicy validator rule
            // during registration, password reset, and profile password
            // change. Defaults mirror NIST SP 800-63B sensible minimums.
            $table->unsignedTinyInteger('password_min_length')->default(10);
            $table->boolean('password_require_mixed_case')->default(true);
            $table->boolean('password_require_number')->default(true);
            $table->boolean('password_require_symbol')->default(true);
            $table->unsignedSmallInteger('password_rotation_days')->nullable();
            $table->unsignedTinyInteger('password_history_count')->default(3);

            // Session hardening. idle_timeout is checked on every
            // authenticated request by the EnforceCompanySecurityPolicy
            // middleware — exceeding it logs the user out. absolute max
            // covers "session survived the laptop being closed for a week".
            $table->unsignedSmallInteger('session_idle_timeout_minutes')->default(60);
            $table->unsignedSmallInteger('session_absolute_max_hours')->default(12);

            // IP allowlist — JSON array of CIDR ranges. Empty = no
            // restriction. Checked before the session is considered
            // authenticated; a mismatch bounces to a 403 page.
            $table->json('ip_allowlist')->nullable();
            $table->boolean('ip_allowlist_enabled')->default(false);

            // Login hardening knobs for the throttled login flow.
            $table->unsignedTinyInteger('max_login_attempts')->default(5);
            $table->unsignedSmallInteger('lockout_minutes')->default(15);

            // Restrict team member invites to specific email domains.
            // JSON array of lowercased domains (no @). Null = any domain.
            $table->json('allowed_email_domains')->nullable();

            // Audit log retention. The AnchorAuditChain job honours this
            // when pruning old log rows — null = keep indefinitely.
            $table->unsignedSmallInteger('audit_retention_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_security_policies');
    }
};
