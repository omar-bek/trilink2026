<?php

namespace App\Models;

use App\Enums\BidStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bid extends Model
{
    use HasFactory, SoftDeletes;

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
}
