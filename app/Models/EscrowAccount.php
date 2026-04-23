<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One escrow account per contract — opened the moment a buyer activates
 * escrow on a signed contract. Funds the buyer deposits go into this
 * account at the bank partner; releases drain it back out as milestones
 * complete. Status flows pending → active → closed.
 */
class EscrowAccount extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'contract_id',
        'bank_partner',
        'external_account_id',
        'currency',
        'total_deposited',
        'total_released',
        'status',
        'activated_at',
        'closed_at',
        'metadata',
        // Phase B — dispute-driven freeze.
        'frozen_at',
        'frozen_by_dispute_id',
        'freeze_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_deposited' => 'decimal:2',
            'total_released' => 'decimal:2',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
            'frozen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(EscrowRelease::class);
    }

    /**
     * Available balance = deposited − released. Negative values are not
     * possible at the schema level (the controller refuses over-releases),
     * but max(0, ...) keeps the UI safe if a manual SQL fix introduces drift.
     */
    public function availableBalance(): float
    {
        return max(0.0, (float) $this->total_deposited - (float) $this->total_released);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_REFUNDED], true);
    }
}
