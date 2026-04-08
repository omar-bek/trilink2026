<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed home for a company's bank details (IBAN / SWIFT / holder name /
 * etc.). Extracted from the old `companies.info_request['bank_details']`
 * JSON blob in Phase 0 / task 0.6 so that:
 *
 *   - IBAN / SWIFT are searchable (covered by indexes on the table)
 *   - Future "verify bank account" workflows have a real place to live
 *   - AML / sanctions screening can join against concrete columns
 */
class CompanyBankDetail extends Model
{
    protected $fillable = [
        'company_id',
        'holder_name',
        'bank_name',
        'branch',
        'iban',
        'swift',
        'currency',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
