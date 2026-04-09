<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint Hardening — denormalized junction row mirroring one entry
 * from `contracts.parties` JSON. Lives purely as a queryable index
 * over the canonical JSON column. The ContractObserver keeps the
 * two in sync on every Contract save.
 *
 * Read-only by convention: never write to this table directly from
 * application code. Mutate `Contract::parties` (or `buyer_company_id`)
 * and let the observer recompute the index.
 */
class ContractParty extends Model
{
    protected $fillable = [
        'contract_id',
        'company_id',
        'role',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
