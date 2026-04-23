<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WpsPayrollBatch extends Model
{
    protected $fillable = [
        'company_id', 'employer_eid', 'agent_id',
        'pay_period_start', 'pay_period_end',
        'employee_count', 'total_gross_aed', 'total_net_aed',
        'status', 'sif_file_path', 'sif_file_hash',
        'submitted_at', 'settled_at', 'submitted_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'pay_period_start' => 'date',
            'pay_period_end' => 'date',
            'submitted_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_gross_aed' => 'decimal:2',
            'total_net_aed' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WpsPayrollLine::class, 'batch_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
