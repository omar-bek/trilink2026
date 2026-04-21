<?php

namespace App\Models;

use App\Enums\DisputeDecisionOutcome;
use App\Enums\DisputeRemedy;
use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dispute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'company_id',
        'raised_by',
        'against_company_id',
        'type',
        'status',
        'title',
        'description',
        // Structured claim.
        'claim_amount',
        'claim_currency',
        'requested_remedy',
        'severity',
        // Lifecycle.
        'escalated_to_government',
        'assigned_to',
        'sla_due_date',
        'response_due_at',
        'acknowledged_at',
        'mediation_started_at',
        'withdrawn_at',
        // Resolution.
        'resolution',
        'resolved_at',
        'decision_outcome',
        'decision_amount',
        'decided_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisputeType::class,
            'status' => DisputeStatus::class,
            'severity' => DisputeSeverity::class,
            'requested_remedy' => DisputeRemedy::class,
            'decision_outcome' => DisputeDecisionOutcome::class,
            'escalated_to_government' => 'boolean',
            'claim_amount' => 'decimal:2',
            'decision_amount' => 'decimal:2',
            'sla_due_date' => 'datetime',
            'response_due_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'mediation_started_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function raisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function againstCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'against_company_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('created_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DisputeOffer::class)->orderByDesc('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DisputeEvent::class)->orderBy('created_at');
    }

    /**
     * Every party to the dispute — claimant company + respondent company.
     * Used by policy checks ("can this user read/write messages on this
     * dispute?") so the logic isn't duplicated across controllers.
     */
    public function partyCompanyIds(): array
    {
        return array_values(array_filter(array_unique([
            $this->company_id,
            $this->against_company_id,
        ])));
    }

    public function isPartyCompany(?int $companyId): bool
    {
        return $companyId !== null && in_array($companyId, $this->partyCompanyIds(), true);
    }

    public function responseOverdue(): bool
    {
        return $this->response_due_at
            && $this->response_due_at->isPast()
            && $this->acknowledged_at === null;
    }

    public function resolutionOverdue(): bool
    {
        return $this->sla_due_date
            && $this->sla_due_date->isPast()
            && ! $this->status?->isTerminal();
    }
}
