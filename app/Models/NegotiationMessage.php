<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegotiationMessage extends Model
{
    use HasFactory;

    public const KIND_TEXT          = 'text';
    public const KIND_COUNTER_OFFER = 'counter_offer';

    public const ROUND_OPEN     = 'open';
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
    ];

    protected function casts(): array
    {
        return [
            'offer'        => 'array',
            'round_number' => 'integer',
        ];
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
