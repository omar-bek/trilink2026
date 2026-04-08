<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per sanctions check executed against a company. Append-only —
 * never updated, never soft-deleted. Compliance auditors need an immutable
 * log of "who checked what, when, against which list, and what came back".
 */
class SanctionsScreening extends Model
{
    use HasFactory;

    public const RESULT_CLEAN    = 'clean';
    public const RESULT_HIT      = 'hit';
    public const RESULT_REVIEW   = 'review';
    public const RESULT_ERROR    = 'error';

    protected $fillable = [
        'company_id',
        'provider',
        'query',
        'result',
        'match_count',
        'matched_entities',
        'triggered_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'matched_entities' => 'array',
            'match_count'      => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
