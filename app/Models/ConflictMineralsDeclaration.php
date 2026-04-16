<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 8 — annual conflict minerals (3TG) declaration, modelled on the
 * OECD Due Diligence Guidance and the standard CMRT template. One row
 * per (company, reporting year).
 */
class ConflictMineralsDeclaration extends Model
{
    use HasFactory;

    public const STATUS_CONFLICT_FREE = 'conflict_free';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'company_id',
        'reporting_year',
        'tin_status',
        'tungsten_status',
        'tantalum_status',
        'gold_status',
        'smelters',
        'policy_url',
    ];

    protected function casts(): array
    {
        return [
            'reporting_year' => 'integer',
            'smelters' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Returns true when all four 3TG metals are declared conflict free.
     * Used by the company profile badge.
     */
    public function isFullyConflictFree(): bool
    {
        return collect([$this->tin_status, $this->tungsten_status, $this->tantalum_status, $this->gold_status])
            ->every(fn ($s) => $s === self::STATUS_CONFLICT_FREE);
    }
}
