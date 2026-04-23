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
        // Phase C — DSO / terms.
        'invoice_issued_at',
        'due_date',
        'paid_date',
        'late_fee_amount',
        'early_discount_amount',
        // Phase H — WHT and reverse-charge.
        'wht_rate',
        'wht_amount',
        'vat_reverse_charge',
        // Phase D — settlement rail + SWIFT GPI.
        'rail',
        'uetr',
        // Phase A hardening — FX lock, Corporate Tax, dual approval,
        // dispute window, cheque + credit-note linkage, accrual flags.
        'fx_rate_snapshot',
        'fx_base_currency',
        'fx_locked_at',
        'amount_in_base',
        'corporate_tax_applicable',
        'corporate_tax_rate',
        'corporate_tax_amount',
        'requires_dual_approval',
        'second_approver_id',
        'second_approved_at',
        'dispute_window_days',
        'disputed_at',
        'dispute_reason',
        'postdated_cheque_id',
        'refund_credit_note_id',
        'settled_at',
        'is_late_fee_accrual',
        'parent_payment_id',
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
            // Phase C.
            'invoice_issued_at' => 'date',
            'due_date' => 'date',
            'paid_date' => 'datetime',
            'late_fee_amount' => 'decimal:2',
            'early_discount_amount' => 'decimal:2',
            // Phase H.
            'wht_rate' => 'decimal:2',
            'wht_amount' => 'decimal:2',
            'vat_reverse_charge' => 'boolean',
            // Phase A hardening.
            'fx_rate_snapshot' => 'decimal:8',
            'fx_locked_at' => 'datetime',
            'amount_in_base' => 'decimal:2',
            'corporate_tax_applicable' => 'boolean',
            'corporate_tax_rate' => 'decimal:2',
            'corporate_tax_amount' => 'decimal:2',
            'requires_dual_approval' => 'boolean',
            'second_approved_at' => 'datetime',
            'disputed_at' => 'datetime',
            'settled_at' => 'datetime',
            'is_late_fee_accrual' => 'boolean',
        ];
    }

    public function postdatedCheque(): BelongsTo
    {
        return $this->belongsTo(PostdatedCheque::class, 'postdated_cheque_id');
    }

    public function refundCreditNote(): BelongsTo
    {
        return $this->belongsTo(TaxCreditNote::class, 'refund_credit_note_id');
    }

    public function secondApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_approver_id');
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'parent_payment_id');
    }

    public function approvals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentApproval::class);
    }

    public function feeAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlatformFeeAllocation::class);
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
            if (! $payment->isDirty('vat_rate') && $payment->vat_rate === null) {
                $payment->vat_rate = TaxRate::resolveFor();
            }

            if (! $payment->vat_amount) {
                $payment->calculateVat();
            }
        });
    }
}
