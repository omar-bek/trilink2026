<?php

namespace App\Models;

use App\Enums\BankGuaranteeStatus;
use App\Enums\BankGuaranteeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankGuarantee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bg_number', 'type', 'governing_rules',
        'applicant_company_id', 'beneficiary_company_id',
        'issuing_bank_id', 'issuing_bank_name', 'issuing_bank_swift', 'issuing_bank_reference',
        'rfq_id', 'bid_id', 'contract_id',
        'amount', 'currency', 'percentage_of_base', 'base_amount',
        'validity_start_date', 'expiry_date', 'claim_period_days',
        'status', 'issued_at', 'activated_at', 'returned_at',
        'advice_document_path', 'advice_document_hash',
        'amount_remaining', 'amount_called',
        'created_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => BankGuaranteeType::class,
            'status' => BankGuaranteeStatus::class,
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount_remaining' => 'decimal:2',
            'amount_called' => 'decimal:2',
            'percentage_of_base' => 'decimal:2',
            'validity_start_date' => 'date',
            'expiry_date' => 'date',
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'returned_at' => 'datetime',
            'metadata' => 'array',
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

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(BankGuaranteeCall::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BankGuaranteeEvent::class);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date
            && $this->expiry_date->diffInDays(now(), false) <= 0
            && $this->expiry_date->diffInDays(now(), false) >= -$days;
    }

    public function remainingLiability(): float
    {
        return (float) ($this->amount_remaining ?? $this->amount) - (float) ($this->amount_called ?? 0);
    }
}
