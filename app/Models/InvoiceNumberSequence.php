<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-(company, series, year) sequential counter for tax invoices and
 * credit notes. The actual atomic allocation lives in
 * {@see \App\Services\Tax\InvoiceNumberAllocator} — this model is just
 * the row holder. Don't update next_value directly; always go through
 * the allocator so the row lock is held correctly.
 */
class InvoiceNumberSequence extends Model
{
    protected $fillable = [
        'company_id',
        'series',
        'year',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_value' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
