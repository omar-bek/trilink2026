<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commercial defaults for a tenant — currency, fiscal year, VAT, payment
 * terms, procurement policy toggles. Consumed by the RFQ / PR / contract
 * creation flows to pre-fill forms so staff never have to re-type the
 * same values project after project.
 */
class CompanyDefaults extends Model
{
    protected $table = 'company_defaults';

    protected $fillable = [
        'company_id',
        'default_currency',
        'default_language',
        'default_timezone',
        'fiscal_year_start_month',
        'default_vat_rate',
        'default_vat_treatment',
        'default_payment_terms_days',
        'late_payment_penalty_percent',
        'contract_approval_threshold_aed',
        'payment_dual_approval_threshold_aed',
        'require_three_quotes_above_threshold',
        'three_quotes_threshold_aed',
        'prefer_local_suppliers',
        'require_icv_certificate',
    ];

    protected function casts(): array
    {
        return [
            'default_vat_rate' => 'decimal:2',
            'require_three_quotes_above_threshold' => 'boolean',
            'prefer_local_suppliers' => 'boolean',
            'require_icv_certificate' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array<string,mixed>
     */
    public static function platformDefaults(): array
    {
        return [
            'default_currency' => 'AED',
            'default_language' => 'en',
            'default_timezone' => 'Asia/Dubai',
            'fiscal_year_start_month' => 1,
            'default_vat_rate' => 5.00,
            'default_vat_treatment' => 'standard',
            'default_payment_terms_days' => 30,
            'late_payment_penalty_percent' => 0,
            'contract_approval_threshold_aed' => null,
            'payment_dual_approval_threshold_aed' => null,
            'require_three_quotes_above_threshold' => false,
            'three_quotes_threshold_aed' => 10000,
            'prefer_local_suppliers' => false,
            'require_icv_certificate' => false,
        ];
    }
}
