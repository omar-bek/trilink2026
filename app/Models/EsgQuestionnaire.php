<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 8 — per-company ESG self-assessment. Stores the raw answer set
 * + the three pillar scores + an overall grade. Recomputed on every
 * submission by EsgScoringService.
 */
class EsgQuestionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'environmental_score',
        'social_score',
        'governance_score',
        'overall_score',
        'grade',
        'answers',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'environmental_score' => 'integer',
            'social_score'        => 'integer',
            'governance_score'    => 'integer',
            'overall_score'       => 'integer',
            'answers'             => 'array',
            'submitted_at'        => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
