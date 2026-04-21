<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_id', 'user_id', 'company_id',
        'body', 'is_internal', 'is_system', 'read_by',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_system' => 'boolean',
            'read_by' => 'array',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
