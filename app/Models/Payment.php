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

    public function calculateVat(): void
    {
        $this->vat_amount = round($this->amount * ($this->vat_rate / 100), 2);
        $this->total_amount = $this->amount + $this->vat_amount;
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (!$payment->vat_amount) {
                $payment->calculateVat();
            }
        });
    }
}
