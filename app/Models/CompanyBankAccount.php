<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per receiving/payout bank account a company maintains. Replaces
 * the single-row CompanyBankDetail legacy model for tenants that operate
 * multiple currencies or branches — the old table stays as a fallback so
 * existing data keeps rendering.
 */
class CompanyBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'label',
        'holder_name', 'bank_name', 'iban', 'swift', 'account_number', 'currency',
        'is_default_receiving', 'is_default_payout', 'is_wps_account', 'is_tax_account',
        'status', 'verified_at', 'verified_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default_receiving' => 'boolean',
            'is_default_payout' => 'boolean',
            'is_wps_account' => 'boolean',
            'is_tax_account' => 'boolean',
            'verified_at' => 'datetime',
            // IBAN is personal-data-adjacent (a natural person's IBAN
            // reveals their banking relationship). Encrypted at rest.
            'iban' => 'encrypted',
            'account_number' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
