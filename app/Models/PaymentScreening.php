<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentScreening extends Model
{
    protected $fillable = [
        'payment_id', 'stage', 'result', 'screened_entity',
        'screened_company_id', 'findings',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'findings' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function screenedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'screened_company_id');
    }
}
