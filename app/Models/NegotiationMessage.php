<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegotiationMessage extends Model
{
    use HasFactory;

    public const KIND_TEXT = 'text';

    public const KIND_COUNTER_OFFER = 'counter_offer';

    public const ROUND_OPEN = 'open';

    public const ROUND_ACCEPTED = 'accepted';

    public const ROUND_REJECTED = 'rejected';

    public const ROUND_COUNTERED = 'countered';

    protected $fillable = [
        'bid_id',
        'sender_id',
        'sender_side',
        'kind',
        'body',
        'offer',
        'round_number',
        'round_status',
        'expires_at',
        'expired_at',
        'responded_at',
        'responded_by',
        'subtotal_excl_tax',
        'tax_amount',
        'total_incl_tax',
        'signed_by_name',
        'signed_at',
        'signature_ip',
        'signature_hash',
    ];

    protected function casts(): array
    {
        return [
            'offer' => 'array',
            'round_number' => 'integer',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'responded_at' => 'datetime',
            'signed_at' => 'datetime',
            'subtotal_excl_tax' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_incl_tax' => 'decimal:2',
        ];
    }

    public function isOpen(): bool
    {
        return $this->round_status === self::ROUND_OPEN;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
