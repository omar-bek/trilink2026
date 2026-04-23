<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankGuaranteeCall extends Model
{
    protected $fillable = [
        'bank_guarantee_id', 'called_by_company_id', 'called_by_user_id',
        'amount', 'currency', 'reason', 'claim_document_path',
        'status', 'honoured_at', 'bank_reference', 'bank_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'honoured_at' => 'datetime',
        ];
    }

    public function bankGuarantee(): BelongsTo
    {
        return $this->belongsTo(BankGuarantee::class);
    }

    public function calledBy(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'called_by_company_id');
    }
}
