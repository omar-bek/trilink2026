<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFeeAllocation extends Model
{
    use HasFactory;

    public const TYPE_TRANSACTION = 'transaction';
    public const TYPE_ESCROW = 'escrow';
    public const TYPE_RECON = 'recon';
    public const TYPE_LISTING = 'listing';

    protected $fillable = [
        'payment_id',
        'fee_type',
        'base_amount',
        'rate',
        'fee_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'fee_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
