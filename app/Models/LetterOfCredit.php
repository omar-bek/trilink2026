<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LetterOfCredit extends Model
{
    use SoftDeletes;

    protected $table = 'letters_of_credit';

    protected $fillable = [
        'contract_id', 'applicant_company_id', 'beneficiary_company_id',
        'lc_number', 'issuing_bank', 'issuing_bank_bic', 'advising_bank', 'advising_bank_bic',
        'form', 'payment_type', 'usance_days', 'transferable', 'confirmed',
        'amount', 'currency', 'tolerance_percent_over', 'tolerance_percent_under',
        'issue_date', 'expiry_date', 'expiry_place', 'latest_shipment_date',
        'incoterm', 'port_of_loading', 'port_of_discharge',
        'goods_description', 'documents_required',
        'status', 'drawn_amount', 'available_amount', 'advice_document_path',
    ];

    protected function casts(): array
    {
        return [
            'transferable' => 'boolean',
            'confirmed' => 'boolean',
            'amount' => 'decimal:2',
            'drawn_amount' => 'decimal:2',
            'available_amount' => 'decimal:2',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'latest_shipment_date' => 'date',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'applicant_company_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'beneficiary_company_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LetterOfCreditEvent::class);
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(LetterOfCreditDrawing::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isWithinLatestShipment(): bool
    {
        return ! $this->latest_shipment_date
            || $this->latest_shipment_date->isFuture();
    }
}
