<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PDPL data-subject request — the user is asking the controller (us)
 * to fulfil one of the rights granted by Federal Decree-Law 45/2021
 * Articles 13-16. See the migration docblock for the legal mapping.
 *
 * Lifecycle: a row is born in `pending` when the user submits the
 * form. The admin queue picks it up, may move it to `in_review` if
 * blockers exist (e.g. active contracts that prevent erasure), then
 * `approved` once any blockers are cleared. Approved requests are
 * picked up by the queue worker and run through the appropriate
 * service ({@see \App\Services\Privacy\DataExportService} for exports,
 * {@see \App\Services\Privacy\DataErasureService} for erasures).
 *
 * Withdrawal: the user can `withdraw` a pending or approved request
 * any time before completion (Article 11 — withdrawal of consent /
 * objection). Withdrawn requests stay in the table for audit.
 */
class PrivacyRequest extends Model
{
    use HasFactory;

    public const TYPE_DATA_EXPORT   = 'data_export';
    public const TYPE_ERASURE       = 'erasure';
    public const TYPE_RECTIFICATION = 'rectification';
    public const TYPE_RESTRICTION   = 'restriction';

    public const ALL_TYPES = [
        self::TYPE_DATA_EXPORT,
        self::TYPE_ERASURE,
        self::TYPE_RECTIFICATION,
        self::TYPE_RESTRICTION,
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
        self::STATUS_WITHDRAWN,
    ];

    protected $fillable = [
        'user_id',
        'request_type',
        'status',
        'requested_at',
        'scheduled_for',
        'completed_at',
        'rejection_reason',
        'fulfillment_metadata',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at'         => 'datetime',
            'scheduled_for'        => 'datetime',
            'completed_at'         => 'datetime',
            'fulfillment_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_IN_REVIEW,
            self::STATUS_APPROVED,
        ], true);
    }

    public function isErasure(): bool
    {
        return $this->request_type === self::TYPE_ERASURE;
    }
}
