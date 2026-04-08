<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot-like record marking a supplier company as belonging exclusively to
 * a parent company. The mere presence of an active row blocks the supplier
 * from bidding on the parent company's RFQs (see BidService::create), while
 * leaving them free to bid on other companies' RFQs.
 */
class CompanySupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'supplier_company_id',
        'status',
        'notes',
        'added_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * True when the given supplier company is locked to the given parent
     * company (active link). This is the canonical check used by BidService.
     */
    public static function isLocked(int $supplierCompanyId, int $parentCompanyId): bool
    {
        return self::query()
            ->where('supplier_company_id', $supplierCompanyId)
            ->where('company_id', $parentCompanyId)
            ->where('status', 'active')
            ->exists();
    }
}
