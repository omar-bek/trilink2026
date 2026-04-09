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
            // Phase 2 (UAE Compliance Roadmap) — PDPL Article 20:
            // beneficial owner records are HIGH-sensitivity personal
            // data (Emirates ID / passport, date of birth, financial
            // profile). Encrypted at rest with AES-256-CBC.
            //
            // Note: Laravel only ships `encrypted`, `encrypted:array`,
            // `encrypted:collection`, `encrypted:json`, `encrypted:object`
            // — there's no native `encrypted:date`. We use plain
            // `encrypted` for date_of_birth and parse it in the
            // accessor below so reads still return a CarbonImmutable.
            'date_of_birth'        => 'encrypted',
            'id_number'            => 'encrypted',
            'source_of_wealth'     => 'encrypted',
            'id_expiry'            => 'date',
            'ownership_percentage' => 'decimal:2',
            'is_pep'               => 'boolean',
            'last_screened_at'     => 'datetime',
            'verified_at'          => 'datetime',
        ];
    }

    /**
     * Accessor: turn the decrypted date_of_birth string into a Carbon
     * instance so calling code (PDFs, KYC review screens, the AML
     * service) can keep using ->format() etc. as if it were a real
     * date column. Returns null when the column is unset.
     */
    public function getDobAttribute(): ?\Carbon\CarbonImmutable
    {
        $raw = $this->date_of_birth;
        if (!$raw) {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
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
