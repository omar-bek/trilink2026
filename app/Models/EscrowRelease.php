<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger entry for an escrow account. Each row is one of:
 *
 *   - deposit: buyer (or bank) wires funds INTO the account
 *   - release: funds leave the account TO the supplier (milestone payment)
 *   - refund:  funds leave the account back TO the buyer (dispute resolved
 *              in their favour, or buyer cancellation before delivery)
 *
 * Sprint 13 / task 3.12 — this table is the audit trail. Never edit a row
 * after insert; corrections happen by inserting an offsetting row instead.
 */
class EscrowRelease extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_RELEASE = 'release';

    public const TYPE_REFUND = 'refund';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_AUTO_SIGNATURE = 'auto_signature';

    public const TRIGGER_AUTO_DELIVERY = 'auto_delivery';

    public const TRIGGER_AUTO_INSPECTION = 'auto_inspection';

    public const TRIGGER_WEBHOOK = 'webhook';

    public const TRIGGER_CRON = 'cron';

    protected $fillable = [
        'escrow_account_id',
        'payment_id',
        'type',
        'amount',
        'currency',
        'milestone',
        'triggered_by',
        'triggered_by_user_id',
        'bank_reference',
        'notes',
        'recorded_at',
        // Set by EscrowService::confirmDepositFromWebhook() the first time a
        // deposit clears at the bank. Doubles as the idempotency token: if
        // the bank retries the same webhook this column is already populated
        // and the service short-circuits without re-incrementing the balance.
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recorded_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function escrowAccount(): BelongsTo
    {
        return $this->belongsTo(EscrowAccount::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
