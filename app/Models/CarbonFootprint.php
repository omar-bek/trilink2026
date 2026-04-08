<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 8 — aggregated carbon footprint entries. Polymorphic via
 * (entity_type, entity_id) so company-level, contract-level, and
 * shipment-level rows live in the same table.
 *
 * The ESG dashboard rolls these up by company over a date range to
 * show Scope 3 totals + a year-over-year trend.
 */
class CarbonFootprint extends Model
{
    use HasFactory;

    public const ENTITY_COMPANY  = 'company';
    public const ENTITY_CONTRACT = 'contract';
    public const ENTITY_SHIPMENT = 'shipment';

    public const SCOPE_DIRECT          = 1;
    public const SCOPE_PURCHASED       = 2;
    public const SCOPE_VALUE_CHAIN     = 3;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'scope',
        'co2e_kg',
        'period_start',
        'period_end',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scope'        => 'integer',
            'co2e_kg'      => 'decimal:2',
            'period_start' => 'date',
            'period_end'   => 'date',
            'metadata'     => 'array',
        ];
    }
}
