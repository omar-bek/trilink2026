<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-company security policy. Exactly one row per company, created on
 * demand the first time the settings page is opened. Callers should
 * always go through `Company::securityPolicy()` which falls back to the
 * platform defaults so unseeded tenants still behave sensibly.
 */
class CompanySecurityPolicy extends Model
{
    protected $fillable = [
        'company_id',
        'enforce_two_factor',
        'two_factor_grace_days',
        'password_min_length',
        'password_require_mixed_case',
        'password_require_number',
        'password_require_symbol',
        'password_rotation_days',
        'password_history_count',
        'session_idle_timeout_minutes',
        'session_absolute_max_hours',
        'ip_allowlist',
        'ip_allowlist_enabled',
        'max_login_attempts',
        'lockout_minutes',
        'allowed_email_domains',
        'audit_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'enforce_two_factor' => 'boolean',
            'password_require_mixed_case' => 'boolean',
            'password_require_number' => 'boolean',
            'password_require_symbol' => 'boolean',
            'ip_allowlist' => 'array',
            'ip_allowlist_enabled' => 'boolean',
            'allowed_email_domains' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Platform-wide defaults applied when a company has not yet
     * customised its security policy. These match the column defaults
     * in the migration — keep them in lockstep.
     *
     * @return array<string,mixed>
     */
    public static function platformDefaults(): array
    {
        return [
            'enforce_two_factor' => false,
            'two_factor_grace_days' => 7,
            'password_min_length' => 10,
            'password_require_mixed_case' => true,
            'password_require_number' => true,
            'password_require_symbol' => true,
            'password_rotation_days' => null,
            'password_history_count' => 3,
            'session_idle_timeout_minutes' => 60,
            'session_absolute_max_hours' => 12,
            'ip_allowlist' => [],
            'ip_allowlist_enabled' => false,
            'max_login_attempts' => 5,
            'lockout_minutes' => 15,
            'allowed_email_domains' => [],
            'audit_retention_days' => null,
        ];
    }
}
