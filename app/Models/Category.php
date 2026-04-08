<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'parent_id',
        'path',
        'level',
        'is_active',
        // UNSPSC mapping (Phase 1 / task 1.9). All fields are nullable —
        // legacy categories without a UNSPSC mapping still work fine.
        'unspsc_code',
        'unspsc_segment',
        'unspsc_family',
        'unspsc_class',
        'unspsc_commodity',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'level'            => 'integer',
            'unspsc_segment'   => 'integer',
            'unspsc_family'    => 'integer',
            'unspsc_class'     => 'integer',
            'unspsc_commodity' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_category')->withTimestamps();
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                $category->path = $parent ? $parent->path . '/' . $category->name : $category->name;
                $category->level = $parent ? $parent->level + 1 : 0;
            } else {
                $category->path = $category->name;
                $category->level = 0;
            }
        });
    }
}
