<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVersion extends Model
{
    protected $fillable = [
        'contract_id',
        'version',
        'snapshot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
