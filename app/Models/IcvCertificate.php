<?php

namespace App\Models;

use App\Services\Procurement\IcvScoringService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * In-Country Value (ICV) certificate held by a supplier company.
 *
 * Issued annually by MoIAT or a delegated government-related buyer
 * (ADNOC, Mubadala, EGA, EWEC, ETIHAD, EMSTEEL). The platform stores
 * a verified copy + the score so buyer-side bid evaluation can
 * weight the price signal by the supplier's local economic footprint.
 *
 * See {@see IcvScoringService} for the
 * composite-score formula.
 */
class IcvCertificate extends Model
{
    use HasFactory, SoftDeletes;

    public const ISSUER_MOIAT = 'moiat';

    public const ISSUER_ADNOC = 'adnoc';

    public const ISSUER_MUBADALA = 'mubadala';

    public const ISSUER_EGA = 'ega';

    public const ISSUER_EWEC = 'ewec';

    public const ISSUER_ETIHAD = 'etihad';

    public const ISSUER_EMSTEEL = 'emsteel';

    public const ISSUER_OTHER = 'other';

    public const ALL_ISSUERS = [
        self::ISSUER_MOIAT,
        self::ISSUER_ADNOC,
        self::ISSUER_MUBADALA,
        self::ISSUER_EGA,
        self::ISSUER_EWEC,
        self::ISSUER_ETIHAD,
        self::ISSUER_EMSTEEL,
        self::ISSUER_OTHER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'issuer',
        'certificate_number',
        'score',
        'issued_date',
        'expires_date',
        'file_path',
        'file_sha256',
        'file_size',
        'original_filename',
        'status',
        'rejection_reason',
        'uploaded_by',
        'verified_by',
        'verified_at',
        // Phase 4.5 — last expiry-reminder threshold sent (60, 30, 7).
        // Used by NotifyExpiringIcvCertificatesCommand to avoid spam.
        'last_expiry_reminder_threshold',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'issued_date' => 'date',
            'expires_date' => 'date',
            'file_size' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && $this->expires_date !== null
            && $this->expires_date->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_date && $this->expires_date->isPast());
    }

    public function daysUntilExpiry(): int
    {
        if (! $this->expires_date) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->expires_date, false));
    }
}
