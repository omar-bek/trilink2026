<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\BidStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bid extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'rfq_id',
        'company_id',
        'provider_id',
        'status',
        'price',
        'currency',
        'delivery_time_days',
        'payment_terms',
        'payment_schedule',
        'items',
        'validity_date',
        'is_anonymous',
        'attachments',
        'ai_score',
        'notes',
        // Phase 2 trade fields — see
        // 2026_04_08_150000_add_trade_fields_to_bids_table.php
        'incoterm',
        'country_of_origin',
        'hs_code',
        'tax_treatment',
        'tax_exemption_reason',
        'tax_rate_snapshot',
        'subtotal_excl_tax',
        'tax_amount',
        'total_incl_tax',
    ];

    protected function casts(): array
    {
        return [
            'status' => BidStatus::class,
            'price' => 'decimal:2',
            'payment_schedule' => 'array',
            'items' => 'array',
            'validity_date' => 'datetime',
            'is_anonymous' => 'boolean',
            'attachments' => 'array',
            'ai_score' => 'array',
            'tax_rate_snapshot' => 'decimal:2',
            'subtotal_excl_tax' => 'decimal:2',
            'tax_amount'        => 'decimal:2',
            'total_incl_tax'    => 'decimal:2',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function negotiationMessages(): HasMany
    {
        return $this->hasMany(NegotiationMessage::class);
    }
}
