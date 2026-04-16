<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Admin asked the company manager for additional info before approving
 * the registration." Typed replacement for the old `companies.info_request`
 * JSON column (Phase 0 / task 0.6).
 *
 * At most one active row per company (enforced by the unique index on
 * company_id). `responded_at` / `responded_by` are set when the manager
 * re-submits via RegisterController::completeInfo, which keeps the row
 * in place for the audit trail — the lifecycle "cleared" state is marked
 * by `responded_at IS NOT NULL`, not by deleting the row.
 */
class CompanyInfoRequest extends Model
{
    protected $fillable = [
        'company_id',
        'items',
        'note',
        'requested_at',
        'requested_by',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /** True while the manager has not yet re-submitted. */
    public function isPending(): bool
    {
        return $this->responded_at === null;
    }
}
