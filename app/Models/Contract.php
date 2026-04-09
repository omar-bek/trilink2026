<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'contract_number',
        'title',
        'description',
        'purchase_request_id',
        'buyer_company_id',
        'branch_id',
        'status',
        'parties',
        'amounts',
        'total_amount',
        'currency',
        'payment_schedule',
        'signatures',
        'terms',
        'start_date',
        'end_date',
        'version',
        'progress_percentage',
        'progress_updates',
        'supplier_documents',
        // Phase 3 / Sprint 11 — set by EscrowService::activate() once the
        // bank account is opened. Lets the contract show page render the
        // escrow status panel without re-querying escrow_accounts.
        'escrow_account_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'parties' => 'array',
            'amounts' => 'array',
            'total_amount' => 'decimal:2',
            'payment_schedule' => 'array',
            'signatures' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'progress_updates' => 'array',
            'supplier_documents' => 'array',
        ];
    }

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * Phase 3 / Sprint 11 — at most one escrow account per contract. Loaded
     * via belongsTo (not hasOne) because the FK lives on the contracts row,
     * which keeps the join cheap on the contract show page.
     */
    public function escrowAccount(): BelongsTo
    {
        return $this->belongsTo(EscrowAccount::class, 'escrow_account_id');
    }

    /**
     * Sprint Hardening — denormalized junction. Lives in
     * `contract_parties` and is kept in sync with the canonical
     * `parties` JSON column by ContractObserver. Use this when you
     * need to query "all contracts where company X is a party"
     * because it's an indexed lookup, not a JSON full-table-scan.
     */
    public function contractParties(): HasMany
    {
        return $this->hasMany(ContractParty::class);
    }

    /**
     * Query scope: every contract that company $companyId is a
     * party of, regardless of role. Replaces the legacy
     * `whereJsonContains('parties', ['company_id' => $cid])` calls.
     * Internally a single indexed JOIN against contract_parties.
     *
     * Use ->forCompany($cid) on any Contract query to get the
     * tenant-scoped result set.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->whereIn('id', function ($sub) use ($companyId) {
            $sub->select('contract_id')
                ->from('contract_parties')
                ->where('company_id', $companyId);
        });
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            if (!$contract->contract_number) {
                $contract->contract_number = 'CTR-' . strtoupper(uniqid());
            }
        });
    }

    public function allPartiesHaveSigned(): bool
    {
        $signatures = $this->signatures ?? [];
        $parties = $this->parties ?? [];

        if (empty($parties)) {
            return false;
        }

        $signedCompanyIds = collect($signatures)->pluck('company_id')->toArray();

        foreach ($parties as $party) {
            if (!in_array($party['company_id'] ?? null, $signedCompanyIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Real progress percentage 0-100. Three sources, in priority order:
     *
     *   1. Explicit `progress_percentage` set by the supplier on the
     *      contract (via the supplier-side update form). Trumps everything
     *      else because the supplier is the source of truth on production.
     *
     *   2. The fraction of payment_schedule milestones that have either
     *      been paid (status = COMPLETED) or signed off (paid_at set).
     *      Tracks "money milestones" which is what the buyer cares about.
     *
     *   3. A status-derived fallback (DRAFT 0, PENDING_SIGNATURES 5,
     *      ACTIVE 50, COMPLETED 100, CANCELLED/TERMINATED 0).
     *
     * Returns an integer 0-100 — never null, so the UI never has to
     * defend against missing data.
     */
    public function realProgress(): int
    {
        if ($this->progress_percentage !== null) {
            return (int) max(0, min(100, $this->progress_percentage));
        }

        $schedule = $this->payment_schedule ?? [];
        if (!empty($schedule)) {
            // Use the eager-loaded payments collection when callers (e.g.
            // ContractController::index) have already hydrated it; otherwise
            // fall back to a count() query. This eliminates the N+1 the
            // contract list pages used to suffer when rendering the
            // progress bar for every row.
            if ($this->relationLoaded('payments')) {
                $paidPayments = $this->payments
                    ->whereIn('status', ['completed', 'processing'])
                    ->count();
            } else {
                $paidPayments = $this->payments()
                    ->whereIn('status', ['completed', 'processing'])
                    ->count();
            }
            if ($paidPayments > 0) {
                return (int) round(($paidPayments / count($schedule)) * 100);
            }
        }

        $statusValue = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;
        return match ($statusValue) {
            'draft'              => 0,
            'pending_signatures' => 5,
            'active', 'signed'   => 50,
            'completed'          => 100,
            'cancelled', 'terminated' => 0,
            default              => 0,
        };
    }
}
