<?php

namespace App\Models;

use App\Enums\AmendmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    protected $fillable = [
        'contract_id',
        'from_version',
        'changes',
        'status',
        'reason',
        'approval_history',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AmendmentStatus::class,
            'changes' => 'array',
            'approval_history' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
