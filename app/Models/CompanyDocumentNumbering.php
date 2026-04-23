<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-company document number series. Each row is one (company,
 * document_type) pair with its own counter. The `next()` helper is
 * the only way to advance the counter — it wraps the increment in a
 * database lock so concurrent issuers never collide.
 */
class CompanyDocumentNumbering extends Model
{
    protected $table = 'company_document_numbering';

    protected $fillable = [
        'company_id', 'document_type', 'prefix', 'format_template',
        'current_sequence', 'reset_year',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Atomically advance the counter and render the next document
     * number. Caller must run this inside a transaction if they also
     * want to persist the resulting number — otherwise a rollback
     * still burns the sequence value, which is the desired behaviour
     * for a legal numbering series (no gaps allowed means we reject
     * the whole action; no duplicates allowed means we never reuse).
     */
    public function next(): string
    {
        $year = (int) now()->format('Y');

        $this->current_sequence = (int) $this->current_sequence + 1;
        if ($this->reset_year !== $year && str_contains($this->format_template ?? '', '{YEAR}')) {
            $this->current_sequence = 1;
            $this->reset_year = $year;
        }
        $this->save();

        return $this->render($year);
    }

    private function render(int $year): string
    {
        $template = $this->format_template ?: '{PREFIX}-{YEAR}-{SEQ:6}';
        $seq = (string) $this->current_sequence;

        $template = preg_replace_callback('/\{SEQ:(\d+)\}/', function ($m) use ($seq) {
            return str_pad($seq, (int) $m[1], '0', STR_PAD_LEFT);
        }, $template);

        return strtr($template, [
            '{PREFIX}' => $this->prefix,
            '{YEAR}' => (string) $year,
            '{MONTH}' => now()->format('m'),
            '{SEQ}' => $seq,
        ]);
    }
}
