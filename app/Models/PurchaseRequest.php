<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'company_id',
        'buyer_id',
        'category_id',
        'sub_category_id',
        'status',
        'items',
        'budget',
        'currency',
        'delivery_location',
        'required_date',
        'approval_history',
        'rfq_generated',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'items' => 'array',
            'delivery_location' => 'array',
            'approval_history' => 'array',
            'rfq_generated' => 'boolean',
            'budget' => 'decimal:2',
            'required_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
