<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reconciliation period (usually a month) for a company. Until the
 * status flips to 'closed' all bank_statement_lines for the window
 * should be accounted for — matched to a Payment/EscrowRelease or
 * explicitly marked as internal/fee. Closing is a guarded action
 * enforced by BankReconciliationService::closePeriod().
 */
class BankReconciliationPeriod extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'period_start',
        'period_end',
        'status',
        'lines_matched',
        'lines_unmatched',
        'opening_balance',
        'closing_balance',
        'closed_at',
        'closed_by',
        'closure_notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
