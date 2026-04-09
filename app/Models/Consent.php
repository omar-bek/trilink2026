<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PDPL consent ledger row. See the migration for the legal background;
 * the short version is: append-only audit trail of every consent the
 * user has granted or withdrawn, with the IP + user-agent that captured
 * it. Don't update existing rows — withdraw by stamping `withdrawn_at`
 * on the same row OR (preferred) insert a new row with `granted_at` and
 * `withdrawn_at` set to keep the original grant intact.
 *
 * The {@see \App\Services\Privacy\ConsentLedger} service is the only
 * legitimate writer for this table; controllers should call into it
 * rather than newing up a Consent directly.
 */
class Consent extends Model
{
    use HasFactory;

    public const TYPE_PRIVACY_POLICY     = 'privacy_policy';
    public const TYPE_DATA_PROCESSING    = 'data_processing';
    public const TYPE_COOKIES_ESSENTIAL  = 'cookies_essential';
    public const TYPE_COOKIES_ANALYTICS  = 'cookies_analytics';
    public const TYPE_MARKETING_EMAIL    = 'marketing_email';
    public const TYPE_THIRD_PARTY_SHARE  = 'third_party_share';

    public const ALL_TYPES = [
        self::TYPE_PRIVACY_POLICY,
        self::TYPE_DATA_PROCESSING,
        self::TYPE_COOKIES_ESSENTIAL,
        self::TYPE_COOKIES_ANALYTICS,
        self::TYPE_MARKETING_EMAIL,
        self::TYPE_THIRD_PARTY_SHARE,
    ];

    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'granted_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'granted_at'   => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->granted_at !== null && $this->withdrawn_at === null;
    }
}
