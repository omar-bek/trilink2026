<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A specialised operating unit inside a Company. Each branch may serve a
 * different category (e.g. logistics vs trading) and is owned by a single
 * branch manager. Records (PR, RFQ, Contract) carry a nullable branch_id
 * so the dashboard can scope listings/approvals to a branch.
 */
class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'name_ar',
        'category_id',
        'address',
        'city',
        'country',
        'branch_manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_manager_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
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
