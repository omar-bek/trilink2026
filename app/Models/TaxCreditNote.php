<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UAE-grade tax credit note. Issued whenever a tax invoice is reversed —
 * refund, dispute settlement, correction, cancellation, post-supply
 * discount, or returned goods. Each credit note has its own sequential
 * number in the CN- series, references back to the original invoice, and
 * carries a snapshot of the affected line items.
 *
 * Required by Cabinet Decision 52/2017 Article 60. Without a credit note,
 * the buyer cannot reverse the input tax they previously claimed on the
 * underlying invoice — and the supplier cannot reduce their output tax on
 * the next VAT return.
 */
class TaxCreditNote extends Model
{
    use HasFactory, SoftDeletes;

    public const REASON_REFUND = 'refund';

    public const REASON_CORRECTION = 'correction';

    public const REASON_CANCELLATION = 'cancellation';

    public const REASON_DISPUTE_SETTLEMENT = 'dispute_settlement';

    public const REASON_POST_SUPPLY_DISCOUNT = 'post_supply_discount';

    public const REASON_GOODS_RETURNED = 'goods_returned';

    public const REASONS = [
        self::REASON_REFUND,
        self::REASON_CORRECTION,
        self::REASON_CANCELLATION,
        self::REASON_DISPUTE_SETTLEMENT,
        self::REASON_POST_SUPPLY_DISCOUNT,
        self::REASON_GOODS_RETURNED,
    ];

    protected $fillable = [
        'credit_note_number',
        'original_invoice_id',
        'issue_date',
        'reason',
        'notes',
        'line_items',
        'subtotal_excl_tax',
        'total_tax',
        'total_inclusive',
        'currency',
        'pdf_path',
        'pdf_sha256',
        'issued_by',
        'issued_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'line_items' => 'array',
            'subtotal_excl_tax' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_inclusive' => 'decimal:2',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class, 'original_invoice_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
