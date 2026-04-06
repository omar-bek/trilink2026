<?php

namespace App\Models;

use App\Enums\RfqStatus;
use App\Enums\RfqType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rfqs';

    protected $fillable = [
        'rfq_number',
        'title',
        'description',
        'company_id',
        'purchase_request_id',
        'type',
        'target_role',
        'target_company_ids',
        'status',
        'items',
        'budget',
        'currency',
        'deadline',
        'delivery_location',
        'is_anonymous',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => RfqType::class,
            'status' => RfqStatus::class,
            'target_company_ids' => 'array',
            'items' => 'array',
            'budget' => 'decimal:2',
            'deadline' => 'datetime',
            'is_anonymous' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Rfq $rfq) {
            if (!$rfq->rfq_number) {
                $rfq->rfq_number = 'RFQ-' . strtoupper(uniqid());
            }
        });
    }
}
