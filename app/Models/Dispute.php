<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'escalated_to_government',
        'assigned_to',
        'sla_due_date',
        'resolution',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisputeType::class,
            'status' => DisputeStatus::class,
            'escalated_to_government' => 'boolean',
            'sla_due_date' => 'datetime',
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
}
