<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtaTaxLedgerEntry extends Model
{
    protected $table = 'fta_tax_ledger';

    protected $fillable = [
        'company_id', 'payment_id', 'tax_type', 'filing_period',
        'direction', 'amount_aed', 'rate_percent',
        'accrued_at', 'routed_at', 'remitted_at',
        'source_bank_account_id', 'destination_bank_account_id',
        'fta_reference', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_aed' => 'decimal:2',
            'rate_percent' => 'decimal:2',
            'accrued_at' => 'datetime',
            'routed_at' => 'datetime',
            'remitted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'source_bank_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'destination_bank_account_id');
    }
}
