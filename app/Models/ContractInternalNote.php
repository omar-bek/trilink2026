<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal team note attached to a Contract — visible ONLY to users
 * from the same company as the author. Used by procurement teams to
 * track internal commentary (price benchmarks, escalation triggers,
 * negotiation strategy) without leaking it to the counter-party.
 *
 * Tenant isolation is enforced at every read path. Do NOT load via
 * `$contract->internalNotes` without applying a `where('company_id', ...)`
 * scope first — the relation is intentionally NOT eager-loadable
 * unscoped because the same contract has notes from BOTH parties
 * stored in the same table.
 */
class ContractInternalNote extends Model
{
    protected $fillable = [
        'contract_id',
        'user_id',
        'company_id',
        'body',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
