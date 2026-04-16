<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 (UAE Compliance Roadmap) — one transmission attempt of a
 * tax invoice through the FTA Peppol pipeline. See the migration
 * docblock for the full lifecycle background.
 *
 * Status transitions are linear except for retries:
 *
 *   queued → submitted → accepted   (happy path)
 *                     → rejected    (FTA validation failed)
 *                     → failed      (transient transmission error;
 *                                    queue worker may retry)
 */
class EInvoiceSubmission extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const ALL_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SUBMITTED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
    ];

    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    // Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening) —
    // discriminator between tax invoices and tax credit notes. Both
    // need FTA clearance through the same Peppol pipeline.
    public const DOC_INVOICE = 'invoice';

    public const DOC_CREDIT_NOTE = 'credit_note';

    protected $fillable = [
        'tax_invoice_id',
        'tax_credit_note_id',
        'document_type',
        'asp_provider',
        'asp_environment',
        'status',
        'payload_xml',
        'payload_sha256',
        'asp_submission_id',
        'asp_acknowledgment_id',
        'fta_clearance_id',
        'asp_response_raw',
        'error_message',
        'submitted_at',
        'acknowledged_at',
        'retries',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'asp_response_raw' => 'array',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'retries' => 'integer',
        ];
    }

    public function taxInvoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class);
    }

    public function taxCreditNote(): BelongsTo
    {
        return $this->belongsTo(TaxCreditNote::class);
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === self::DOC_CREDIT_NOTE;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_REJECTED], true);
    }
}
