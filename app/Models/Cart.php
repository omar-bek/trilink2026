<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 4 / Sprint 16 — buyer's open shopping cart. Each user owns at
 * most one cart in the OPEN state at any given time; CartService::current
 * is the canonical accessor that creates one on demand.
 */
class Cart extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Total quantity across all lines — what the topbar badge displays.
     *
     * Three resolution paths in order of cheapness:
     *   1. `items_sum_quantity` populated by `withSum('items', 'quantity')`
     *      on the original query — zero extra round-trips.
     *   2. The `items` relation if it's already eager-loaded — sum from
     *      the in-memory collection, no DB hit.
     *   3. SQL aggregate as a last resort.
     *
     * Note: do NOT use `items_count` here. `withCount` returns the row
     * count, not the sum of quantities — a cart with one line of qty=10
     * would render as "1" in the topbar instead of "10".
     */
    public function totalQuantity(): int
    {
        if ($this->items_sum_quantity !== null) {
            return (int) $this->items_sum_quantity;
        }
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum('quantity');
        }

        return (int) $this->items()->sum('quantity');
    }

    /**
     * Per-currency line totals. Returns ['AED' => 1234.50, 'USD' => ...]
     * because the cart can mix suppliers in different currencies. The
     * checkout view uses this to render a per-currency summary instead of
     * collapsing everything into a single number that wouldn't make sense.
     */
    public function totalsByCurrency(): array
    {
        $totals = [];
        foreach ($this->items as $item) {
            $key = $item->currency ?: 'AED';
            $totals[$key] = ($totals[$key] ?? 0) + ($item->quantity * (float) $item->unit_price);
        }

        return $totals;
    }
}
