<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $table = 'rfqs';

    protected $fillable = [
        'rfq_number',
        'title',
        'description',
        'company_id',
        'branch_id',
        'purchase_request_id',
        'type',
        'target_role',
        'target_company_ids',
        'status',
        'items',
        'budget',
        'currency',
        'deadline',
        'delivery_location',
        'is_anonymous',
        'category_id',
        'is_auction',
        'auction_starts_at',
        'auction_ends_at',
        'reserve_price',
        'bid_decrement',
        'anti_snipe_seconds',
        // Phase 4 (UAE Compliance Roadmap) — ICV scoring weighting.
        'icv_weight_percentage',
        'icv_minimum_score',
        // Phase 4.5 — issuer allowlist. Empty/null = any verified issuer.
        'icv_required_issuers',
    ];

    protected function casts(): array
    {
        return [
            'type' => RfqType::class,
            'status' => RfqStatus::class,
            'target_company_ids' => 'array',
            'items' => 'array',
            // delivery_location is a TEXT column holding a JSON blob
            // ({address, city, country, terms}). Cast so the marketplace
            // card and the RFQ detail page get a real array instead of
            // leaking the raw JSON string into the UI.
            'delivery_location' => 'array',
            'budget' => 'decimal:2',
            'deadline' => 'datetime',
            'is_anonymous' => 'boolean',
            'is_auction' => 'boolean',
            'auction_starts_at' => 'datetime',
            'auction_ends_at' => 'datetime',
            'reserve_price' => 'decimal:2',
            'bid_decrement' => 'decimal:2',
            'anti_snipe_seconds' => 'integer',
            // Phase 4 — ICV weighting on bid evaluation.
            'icv_weight_percentage' => 'integer',
            'icv_minimum_score' => 'decimal:2',
            // Phase 4.5 — issuer allowlist (e.g. ['adnoc'] for ADNOC tenders).
            'icv_required_issuers' => 'array',
        ];
    }

    /**
     * True when this RFQ is a live, in-window reverse auction. Bids during
     * an auction window are subject to bid_decrement and anti-snipe rules.
     */
    public function isLiveAuction(): bool
    {
        if (! $this->is_auction) {
            return false;
        }
        $now = now();

        return $this->auction_starts_at && $this->auction_ends_at
            && $now->gte($this->auction_starts_at)
            && $now->lte($this->auction_ends_at);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function isOpen(): bool
    {
        return $this->status === RfqStatus::OPEN;
    }

    /**
     * Deterministic "how well does this supplier fit this RFQ?" score on a
     * 0-100 scale. Replaces the old `70 + ($id * 13) % 31` stub used by
     * RfqController::supplierIndex + supplierShow.
     *
     * Weighting (see docs/EXECUTION_PLAN.md §Sprint 3, task 1.4):
     *
     *   category overlap ............. 40   (RFQ category ∈ supplier's categories)
     *   country match ................ 20   (supplier country == RFQ company country)
     *   prior bid history ............ 15   (supplier has bid on this buyer before)
     *   verification tier ............ 10   (scaled by VerificationLevel::rank)
     *   recent activity .............. 10   (any bid in the last 30 days)
     *   certifications on file .......  5   (any non-empty certifications JSON)
     *
     *   Total ....................... 100
     *
     * The score is deterministic — two calls with the same inputs return the
     * same number — so pagination / caching stay stable. Callers can pass a
     * pre-loaded supplier (to avoid N+1s) or let the method do the lookup.
     */
    public function matchScoreFor(Company $supplier): int
    {
        // 1) Category overlap — 40 points. Full credit if the RFQ's category
        //    is one of the supplier's declared categories. Partial credit (15)
        //    if they share the same parent category (sibling products).
        $score = 0;

        if ($this->category_id) {
            $supplierCategoryIds = $supplier->relationLoaded('categories')
                ? $supplier->categories->pluck('id')->all()
                : $supplier->categories()->pluck('categories.id')->all();

            if (in_array($this->category_id, $supplierCategoryIds, true)) {
                $score += 40;
            } else {
                // Sibling check: any category the supplier carries that shares
                // the RFQ category's parent still counts for something.
                $parentId = optional($this->category)->parent_id;
                if ($parentId) {
                    $siblings = Category::where('parent_id', $parentId)
                        ->pluck('id')
                        ->all();
                    if (array_intersect($siblings, $supplierCategoryIds)) {
                        $score += 15;
                    }
                }
            }
        }

        // 2) Country match — 20 points. Flat: same ISO country as the buyer.
        if ($supplier->country && $this->company?->country
            && strcasecmp($supplier->country, $this->company->country) === 0) {
            $score += 20;
        }

        // 3) Bid history with this buyer — 15 points. Repeat suppliers are
        //    known-good. We look at any historic bid from this supplier on any
        //    RFQ belonging to the same buyer company.
        if ($this->company_id) {
            $hasHistory = Bid::where('company_id', $supplier->id)
                ->whereHas('rfq', fn ($q) => $q->where('company_id', $this->company_id))
                ->where('rfq_id', '!=', $this->id)
                ->exists();
            if ($hasHistory) {
                $score += 15;
            }
        }

        // 4) Verification tier — up to 10 points, scaled by rank (0..4).
        $rank = $supplier->verification_level?->rank() ?? 0;
        $score += (int) round(($rank / 4) * 10);

        // 5) Recent activity — 10 points if the supplier has bid anywhere in
        //    the last 30 days (i.e. they're active, not a stale profile).
        $recentlyActive = Bid::where('company_id', $supplier->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
        if ($recentlyActive) {
            $score += 10;
        }

        // 6) Certifications on file — 5 points for any non-empty certifications
        //    list. Signals the supplier took the trouble to upload docs.
        $certs = $supplier->certifications ?? [];
        if (is_array($certs) && count($certs) > 0) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    protected static function booted(): void
    {
        static::creating(function (Rfq $rfq) {
            if (! $rfq->rfq_number) {
                $rfq->rfq_number = 'RFQ-'.strtoupper(uniqid());
            }
        });
    }
}
