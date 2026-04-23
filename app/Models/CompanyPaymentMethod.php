<?php

namespace App\Models;

use App\Enums\PaymentRail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-level payment-rail preferences. One row per PaymentRail a
 * company has an opinion about — rails with no row fall back to the
 * platform default (accept everything). The PaymentController consults
 * this table before rendering the settlement form so rails a tenant
 * has turned off never appear as an option.
 */
class CompanyPaymentMethod extends Model
{
    protected $fillable = [
        'company_id', 'rail',
        'accept_incoming', 'allow_outgoing',
        'min_amount_aed', 'max_amount_aed', 'preferred_above_aed',
        'require_dual_approval', 'receiving_account_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'accept_incoming' => 'boolean',
            'allow_outgoing' => 'boolean',
            'require_dual_approval' => 'boolean',
            'rail' => PaymentRail::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function receivingAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'receiving_account_id');
    }
}
