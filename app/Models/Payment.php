<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'company_id',
        'recipient_company_id',
        'buyer_id',
        'status',
        'amount',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'currency',
        'milestone',
        'payment_gateway',
        'gateway_payment_id',
        'gateway_order_id',
        'retry_count',
        'approved_at',
        'approved_by',
        'rejection_reason',
        // Phase 3 / Sprint 11 — set by EscrowService::releaseFor() when this
        // payment was satisfied via escrow rather than a card/bank gateway.
        'escrow_release_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recipientCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'recipient_company_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Phase 3 / Sprint 11 — back-reference to the EscrowRelease ledger entry
     * that satisfied this payment, when the milestone was paid via escrow.
     * Null for payments processed through Stripe/PayPal directly.
     */
    public function escrowRelease(): BelongsTo
    {
        return $this->belongsTo(EscrowRelease::class);
    }

    public function calculateVat(): void
    {
        $this->vat_amount = round($this->amount * ($this->vat_rate / 100), 2);
        $this->total_amount = $this->amount + $this->vat_amount;
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            // If the caller did not specify a VAT rate at all, fall back to the
            // active platform-managed rate (admin/government UI). Explicit 0
            // (e.g. tax-exempt items) is respected and not overridden.
            if (!$payment->isDirty('vat_rate') && $payment->vat_rate === null) {
                $payment->vat_rate = TaxRate::resolveFor();
            }

            if (!$payment->vat_amount) {
                $payment->calculateVat();
            }
        });
    }
}
