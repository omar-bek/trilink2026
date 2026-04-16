<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 8 — annual modern slavery statement, modelled on the UK Act +
 * UAE Labour Law disclosure requirements. Each row is one reporting
 * year for one company.
 */
class ModernSlaveryStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'reporting_year',
        'statement',
        'controls',
        'board_approved',
        'approved_at',
        'signed_by_name',
        'signed_by_title',
    ];

    protected function casts(): array
    {
        return [
            'reporting_year' => 'integer',
            'controls' => 'array',
            'board_approved' => 'boolean',
            'approved_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
