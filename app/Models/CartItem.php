<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 / Sprint 16 — single line on a Cart. Snapshots price + name +
 * variant attributes at add time so the buyer never sees a price change
 * mid-checkout and historical reorder still works after a variant deletion.
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'supplier_company_id',
        'quantity',
        'unit_price',
        'currency',
        'name_snapshot',
        'attributes_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity'            => 'integer',
            'unit_price'          => 'decimal:2',
            'attributes_snapshot' => 'array',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function lineTotal(): float
    {
        return round($this->quantity * (float) $this->unit_price, 2);
    }
}
