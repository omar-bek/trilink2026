<?php

namespace App\Models;

use App\Enums\ChequeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cheque drawn against the issuer's UAE bank account. Typically post-dated
 * (presentation_date > today) so the bank cannot cash it before the agreed
 * day. Widely used in B2B — construction, trading, rental — and regulated
 * by UAE Commercial Transactions Law 18/1993 Articles 596-641 plus the
 * 2022 reform that decriminalised single-bounce cheques.
 *
 * Linked to a Payment (so the Payment settles when the cheque clears) and
 * optionally to a Contract. Events (issued → deposited → cleared / returned)
 * are appended to cheque_events for audit.
 */
class PostdatedCheque extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cheque_number',
        'issuer_company_id',
        'beneficiary_company_id',
        'contract_id',
        'payment_id',
        'drawer_bank_name',
        'drawer_bank_swift',
        'drawer_account_iban',
        'issue_date',
        'presentation_date',
        'amount',
        'currency',
        'status',
        'return_reason',
        'image_path',
        'image_sha256',
        'deposited_at',
        'cleared_at',
        'returned_at',
        'notes',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChequeStatus::class,
            'issue_date' => 'date',
            'presentation_date' => 'date',
            'amount' => 'decimal:2',
            'deposited_at' => 'datetime',
            'cleared_at' => 'datetime',
            'returned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'issuer_company_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'beneficiary_company_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChequeEvent::class);
    }

    public function isPresentable(\Carbon\CarbonInterface $on): bool
    {
        return ! $on->isBefore($this->presentation_date);
    }
}
