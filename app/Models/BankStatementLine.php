<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_id', 'value_date', 'booking_date',
        'amount', 'currency', 'direction',
        'counterparty_name', 'counterparty_iban',
        'reference', 'description',
        'matched_type', 'matched_id', 'match_status', 'matched_by', 'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'booking_date' => 'date',
            'amount' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }
}
