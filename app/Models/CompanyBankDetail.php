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

    protected function casts(): array
    {
        return [
            // Phase 2 (UAE Compliance Roadmap) — PDPL Article 20 + AML
            // best practice: bank account identifiers + the natural
            // person they belong to are sensitive personal data and
            // sensitive financial data simultaneously. Encrypted at
            // rest with AES-256-CBC.
            //
            // Note: encrypting these breaks raw column search; the
            // join-on-IBAN flow used by the screening pipeline now
            // has to load the row into memory and decrypt before
            // comparing. That's a tiny perf hit on a small table
            // and an acceptable trade for the compliance gain.
            'holder_name' => 'encrypted',
            'iban' => 'encrypted',
            'swift' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
