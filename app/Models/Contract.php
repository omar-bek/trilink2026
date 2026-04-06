<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_number',
        'title',
        'description',
        'purchase_request_id',
        'buyer_company_id',
        'status',
        'parties',
        'amounts',
        'total_amount',
        'currency',
        'payment_schedule',
        'signatures',
        'terms',
        'start_date',
        'end_date',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'parties' => 'array',
            'amounts' => 'array',
            'total_amount' => 'decimal:2',
            'payment_schedule' => 'array',
            'signatures' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            if (!$contract->contract_number) {
                $contract->contract_number = 'CTR-' . strtoupper(uniqid());
            }
        });
    }

    public function allPartiesHaveSigned(): bool
    {
        $signatures = $this->signatures ?? [];
        $parties = $this->parties ?? [];

        if (empty($parties)) {
            return false;
        }

        $signedCompanyIds = collect($signatures)->pluck('company_id')->toArray();

        foreach ($parties as $party) {
            if (!in_array($party['company_id'] ?? null, $signedCompanyIds)) {
                return false;
            }
        }

        return true;
    }
}
