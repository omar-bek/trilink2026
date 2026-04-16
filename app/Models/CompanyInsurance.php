<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Single insurance policy on file for a company. Phase 2 / Sprint 10.
 *
 * Lifecycle: pending → verified | rejected. Verified policies whose
 * `expires_at` has passed are flipped to `expired` by the daily document
 * housekeeping job (same job that handles CompanyDocument expiry).
 *
 * The "Insured" badge on the supplier profile is rendered only when the
 * company has at least one VERIFIED + non-expired policy.
 */
class CompanyInsurance extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const TYPES = [
        'cargo',
        'public_liability',
        'professional_indemnity',
        'product_liability',
        'workers_comp',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'type',
        'insurer',
        'policy_number',
        'coverage_amount',
        'currency',
        'starts_at',
        'expires_at',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'status',
        'rejection_reason',
        'uploaded_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'coverage_amount' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
