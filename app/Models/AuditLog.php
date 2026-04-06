<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'resource_type',
        'resource_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'status',
        'hash',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->hash = hash('sha256', json_encode([
                $log->user_id,
                $log->action,
                $log->resource_type,
                $log->resource_id,
                now()->toISOString(),
            ]));
        });
    }
}
