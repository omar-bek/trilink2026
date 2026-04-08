<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Beneficial owner row — a natural person who owns or controls 25%+
 * of a Company on the platform. Required disclosure for Gold tier and
 * above per UAE PDPL + GCC AML guidance.
 *
 * One company can have many beneficial owners; the sum of their
 * `ownership_percentage` should equal or exceed 100% for the disclosure
 * to be considered "complete" (validation enforced at the controller).
 */
class BeneficialOwner extends Model
{
    use HasFactory, SoftDeletes;

    public const ID_TYPES = ['passport', 'emirates_id', 'gcc_id', 'national_id', 'other'];

    public const ROLES = ['shareholder', 'director', 'ubo', 'controller'];

    public const SCREENING_RESULTS = ['clean', 'hit', 'review'];

    protected $fillable = [
        'company_id',
        'full_name',
        'nationality',
        'date_of_birth',
        'id_type',
        'id_number',
        'id_expiry',
        'ownership_percentage',
        'role',
        'is_pep',
        'source_of_wealth',
        'last_screened_at',
        'screening_result',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'        => 'date',
            'id_expiry'            => 'date',
            'ownership_percentage' => 'decimal:2',
            'is_pep'               => 'boolean',
            'last_screened_at'     => 'datetime',
            'verified_at'          => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
