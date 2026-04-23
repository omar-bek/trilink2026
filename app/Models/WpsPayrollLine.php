<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpsPayrollLine extends Model
{
    protected $fillable = [
        'batch_id', 'employee_lcpn', 'employee_name', 'iban', 'bank_code',
        'basic_salary', 'housing_allowance', 'other_allowances',
        'deductions', 'gross_salary', 'net_salary',
        'leave_days', 'working_days',
    ];

    protected function casts(): array
    {
        return [
            // IBAN + LCPN are personal identifiers of natural persons —
            // covered by the PDPL. Encrypted at rest so a DB snapshot
            // leak doesn't expose payroll details.
            'iban' => 'encrypted',
            'employee_lcpn' => 'encrypted',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'other_allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WpsPayrollBatch::class, 'batch_id');
    }
}
