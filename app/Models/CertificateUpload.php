<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 8 (UAE Compliance Roadmap) — Tier 3 compliance certificate.
 * See the migration for the full legal background.
 */
class CertificateUpload extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_COO   = 'coo';
    public const TYPE_ECAS  = 'ecas';
    public const TYPE_HALAL = 'halal';
    public const TYPE_GSO   = 'gso';
    public const TYPE_ISO   = 'iso';
    public const TYPE_OTHER = 'other';

    public const ALL_TYPES = [
        self::TYPE_COO, self::TYPE_ECAS, self::TYPE_HALAL,
        self::TYPE_GSO, self::TYPE_ISO, self::TYPE_OTHER,
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'company_id', 'shipment_id', 'product_id',
        'certificate_type', 'certificate_number', 'issuer',
        'issued_date', 'expires_date',
        'file_path', 'file_sha256', 'file_size', 'original_filename',
        'status', 'rejection_reason',
        'uploaded_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_date'  => 'date',
            'expires_date' => 'date',
            'file_size'    => 'integer',
            'verified_at'  => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && ($this->expires_date === null || $this->expires_date->isFuture());
    }
}
