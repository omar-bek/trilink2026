<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One credit score fetch from a bureau, append-only. Phase 2 / Sprint 10.
 *
 * History matters: a deteriorating score across multiple rows is a
 * leading indicator of supplier risk that the latest-only column on
 * the company can't show. Reports stay forever for compliance.
 */
class CreditScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'provider',
        'score',
        'band',
        'reasons',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'reasons'     => 'array',
            'reported_at' => 'datetime',
            'score'       => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
