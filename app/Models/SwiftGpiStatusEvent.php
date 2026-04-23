<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwiftGpiStatusEvent extends Model
{
    protected $fillable = [
        'payment_id', 'uetr', 'status', 'status_reason',
        'from_bic', 'to_bic', 'amount', 'currency',
        'charges_amount', 'charges_currency', 'fx_rate',
        'originator_time', 'received_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'originator_time' => 'datetime',
            'received_at' => 'datetime',
            'amount' => 'decimal:2',
            'charges_amount' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
