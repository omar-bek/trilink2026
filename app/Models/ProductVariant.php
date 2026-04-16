<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 4 / Sprint 15 — SKU-level variation of a parent Product. The
 * supplier creates variants on the product edit page; buyers pick a
 * variant before adding to cart. Products without variants behave
 * exactly as they did pre-Phase-4.
 */
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'price_modifier',
        'stock_qty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price_modifier' => 'decimal:2',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Final sale price = parent product base + this variant's modifier.
     * Modifier may be negative for smaller-tier discounts.
     */
    public function effectivePrice(): float
    {
        $base = (float) ($this->product?->base_price ?? 0);

        return max(0.0, round($base + (float) $this->price_modifier, 2));
    }

    public function isPurchasable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->stock_qty !== null && $this->stock_qty <= 0) {
            return false;
        }

        return true;
    }
}
