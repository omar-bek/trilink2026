<?php

namespace App\Models;

use App\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A catalog product listed by a supplier company. Buyers can purchase
 * products directly via the Buy-Now flow (creates a Contract immediately)
 * without going through the RFQ → Bid → Acceptance round-trip.
 *
 * Designed for standard, repeatable goods. Custom/large orders should
 * still use the RFQ workflow.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'sku',
        'hs_code',
        'name',
        'name_ar',
        'description',
        'base_price',
        'currency',
        'unit',
        'min_order_qty',
        'stock_qty',
        'lead_time_days',
        'images',
        'specs',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price'      => 'decimal:2',
            'min_order_qty'   => 'integer',
            'stock_qty'       => 'integer',
            'lead_time_days'  => 'integer',
            'images'          => 'array',
            'specs'           => 'array',
            'is_active'       => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Phase 4 / Sprint 15 — SKU-level variations of this product (size,
     * color, tier). Empty when the supplier hasn't created any, in which
     * case the buyer just adds the base product to cart.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Convenience: returns true if the product can be purchased now (active,
     * has stock if tracked, and supplier company is in good standing).
     */
    public function isPurchasable(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->stock_qty !== null && $this->stock_qty <= 0) {
            return false;
        }
        return true;
    }

    /**
     * Phase 4 — lowest sale price across the parent product and any
     * active variants. Used by the catalog grid to render "From X" when
     * variants exist with different price modifiers.
     */
    public function lowestPrice(): float
    {
        $base = (float) $this->base_price;
        if (!$this->relationLoaded('variants') || $this->variants->isEmpty()) {
            return $base;
        }

        $prices = $this->variants
            ->where('is_active', true)
            ->map(fn (ProductVariant $v) => max(0.0, $base + (float) $v->price_modifier))
            ->push($base)
            ->all();

        return round(min($prices), 2);
    }
}
