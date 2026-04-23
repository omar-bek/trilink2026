<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankGuaranteeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bank_guarantee_id', 'actor_user_id', 'event', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function bankGuarantee(): BelongsTo
    {
        return $this->belongsTo(BankGuarantee::class);
    }
}
