<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the back-and-forth conversation attached to a single
 * {@see ContractAmendment}. The amendment itself carries the formal
 * propose/approve/reject decision; this thread carries the WORDS the
 * two parties used to negotiate that decision.
 *
 * Append-only — there is no update path. Editing a posted message
 * would muddy the audit log; if a sender misspoke they post a follow-up.
 */
class ContractAmendmentMessage extends Model
{
    protected $fillable = [
        'contract_amendment_id',
        'user_id',
        'company_id',
        'body',
    ];

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(ContractAmendment::class, 'contract_amendment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
