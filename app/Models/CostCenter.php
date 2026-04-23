<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name', 'name_ar',
        'annual_budget_aed', 'committed_aed', 'fiscal_year',
        'owner_user_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_budget_aed' => 'decimal:2',
            'committed_aed' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function remainingBudget(): ?float
    {
        if ($this->annual_budget_aed === null) {
            return null;
        }

        return (float) $this->annual_budget_aed - (float) $this->committed_aed;
    }
}
