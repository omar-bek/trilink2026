<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'postdated_cheque_id',
        'event',
        'actor_user_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(PostdatedCheque::class, 'postdated_cheque_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
