<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per internal-approval decision (approve OR reject) on a
 * contract that crossed the buyer company's
 * `approval_threshold_aed`. Captures who decided, when, with optional
 * notes — feeds the audit log AND the approvals dashboard.
 */
class ContractApproval extends Model
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    protected $fillable = [
        'contract_id',
        'user_id',
        'company_id',
        'decision',
        'notes',
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
