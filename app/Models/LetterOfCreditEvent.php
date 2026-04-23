<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterOfCreditEvent extends Model
{
    protected $fillable = [
        'letter_of_credit_id', 'event', 'actor_user_id',
        'amount', 'notes', 'metadata', 'created_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
