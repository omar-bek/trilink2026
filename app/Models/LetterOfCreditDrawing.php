<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterOfCreditDrawing extends Model
{
    protected $fillable = [
        'letter_of_credit_id', 'amount', 'currency',
        'presentation_date', 'honoured_date',
        'presented_by_user_id', 'discrepancies',
        'status', 'document_bundle_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'presentation_date' => 'date',
            'honoured_date' => 'date',
            'discrepancies' => 'array',
        ];
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presented_by_user_id');
    }
}
