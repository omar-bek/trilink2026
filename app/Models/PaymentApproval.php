<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dual-approval ledger for payments above the contract threshold (default
 * AED 500k). Every approval click is a row so the trail survives role
 * changes, user deletions, and ensures no silent second-signer bypass.
 */
class PaymentApproval extends Model
{
    use HasFactory;

    public const ROLE_PRIMARY = 'primary';
    public const ROLE_SECONDARY = 'secondary';

    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';

    protected $fillable = [
        'payment_id',
        'approver_id',
        'role',
        'action',
        'notes',
        'amount_snapshot',
        'currency_snapshot',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'amount_snapshot' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
