<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UAE-grade tax invoice. Issued automatically when a Payment transitions
 * to COMPLETED via the IssueTaxInvoiceJob, or manually by a finance user
 * from the admin. Once issued, the row is immutable except for the
 * void/voided_by/voided_at/void_reason columns — see
 * {@see \App\Services\Tax\TaxInvoiceService::voidInvoice()}.
 *
 * Field semantics map 1:1 to FTA Tax Invoice requirements (Federal
 * Decree-Law 8/2017 Article 65 + Cabinet Decision 52/2017 Article 59):
 *
 *   invoice_number    → "Sequential number that uniquely identifies the
 *                        document" (Art. 65 (a))
 *   issue_date        → "Date of issuance" (Art. 65 (b))
 *   supply_date       → "Date of supply if different from issue date"
 *   supplier_*        → Issuer name + TRN + address (Art. 65 (c)(d))
 *   buyer_*           → Recipient name + TRN + address (Art. 65 (e))
 *   line_items        → "Description of goods/services + unit price + qty
 *                        + tax rate + tax amount" (Art. 65 (f-i))
 *   subtotal_excl_tax → "Total amount excluding tax"
 *   total_tax         → "Total amount of tax payable in AED"
 *   total_inclusive   → "Total amount payable inclusive of tax"
 */
class TaxInvoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOIDED = 'voided';

    // Phase 1.5 (UAE Compliance Roadmap — post-implementation hardening).
    // Cabinet Decision 52/2017 Article 59(1)(j) — the legal treatment
    // must be visible on the invoice document itself.
    public const VAT_STANDARD                 = 'standard';
    public const VAT_REVERSE_CHARGE           = 'reverse_charge';
    public const VAT_DESIGNATED_ZONE_INTERNAL = 'designated_zone_internal';
    public const VAT_EXEMPT                   = 'exempt';
    public const VAT_ZERO_RATED               = 'zero_rated';
    public const VAT_OUT_OF_SCOPE             = 'out_of_scope';

    public const ALL_VAT_TREATMENTS = [
        self::VAT_STANDARD,
        self::VAT_REVERSE_CHARGE,
        self::VAT_DESIGNATED_ZONE_INTERNAL,
        self::VAT_EXEMPT,
        self::VAT_ZERO_RATED,
        self::VAT_OUT_OF_SCOPE,
    ];

    protected $fillable = [
        'invoice_number',
        'contract_id',
        'payment_id',
        'issue_date',
        'supply_date',
        'supplier_company_id',
        'supplier_trn',
        'supplier_name',
        'supplier_address',
        'supplier_country',
        'buyer_company_id',
        'buyer_trn',
        'buyer_name',
        'buyer_address',
        'buyer_country',
        'line_items',
        'subtotal_excl_tax',
        'total_discount',
        'total_tax',
        'total_inclusive',
        'currency',
        'vat_treatment',
        'pdf_path',
        'pdf_sha256',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
        'issued_by',
        'issued_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'        => 'date',
            'supply_date'       => 'date',
            'line_items'        => 'array',
            'subtotal_excl_tax' => 'decimal:2',
            'total_discount'    => 'decimal:2',
            'total_tax'         => 'decimal:2',
            'total_inclusive'   => 'decimal:2',
            'voided_at'         => 'datetime',
            'issued_at'         => 'datetime',
            'metadata'          => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(TaxCreditNote::class, 'original_invoice_id');
    }

    /**
     * Phase 5 (UAE Compliance Roadmap) — every transmission attempt
     * of this invoice through the FTA Peppol pipeline. The latest
     * row's status is what the admin queue surfaces.
     */
    public function eInvoiceSubmissions(): HasMany
    {
        return $this->hasMany(EInvoiceSubmission::class);
    }

    public function latestEInvoiceSubmission(): ?EInvoiceSubmission
    {
        return $this->eInvoiceSubmissions()->latest('id')->first();
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }
}
