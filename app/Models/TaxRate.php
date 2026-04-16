<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tax/VAT rate that the platform applies to contracts and payments.
 *
 * The lookup hierarchy (most specific wins):
 *   1. Active rate matching category + country
 *   2. Active rate matching category only
 *   3. Active rate matching country only
 *   4. The active rate flagged is_default = true
 *
 * Use {@see TaxRate::resolveFor()} for the lookup — never query directly from
 * services so the precedence stays in one place.
 */
class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'rate',
        'category_id',
        'country',
        'is_active',
        'is_default',
        'effective_from',
        'effective_to',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Resolve the most specific active tax rate for a given category/country.
     * Returns the rate as a float (e.g. 5.00). Falls back to 0 if there is
     * no active rate at all.
     */
    public static function resolveFor(?int $categoryId = null, ?string $country = null): float
    {
        $today = now()->toDateString();

        $base = self::query()
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            });

        // Most specific: category + country
        if ($categoryId && $country) {
            $rate = (clone $base)
                ->where('category_id', $categoryId)
                ->where('country', $country)
                ->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        }

        // Category only
        if ($categoryId) {
            $rate = (clone $base)
                ->where('category_id', $categoryId)
                ->whereNull('country')
                ->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        }

        // Country only
        if ($country) {
            $rate = (clone $base)
                ->whereNull('category_id')
                ->where('country', $country)
                ->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        }

        // Default fallback
        $rate = (clone $base)
            ->where('is_default', true)
            ->value('rate');

        return $rate !== null ? (float) $rate : 0.0;
    }
}
