<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'registration_number',
        'tax_number',
        'type',
        'status',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'logo',
        'documents',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'status' => CompanyStatus::class,
            'documents' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'company_category')->withTimestamps();
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function buyerContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'buyer_company_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recipient_company_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::ACTIVE;
    }

    /**
     * Cascade policy when a company is deleted (soft OR force).
     *
     * RULE: companies are the foundation of the platform. Deleting a company
     * removes its **people** (users) and **owned look-up rows** (category
     * pivot, uploads), but it MUST preserve the transactional history that
     * the rest of the platform relies on:
     *
     *   PRESERVED  →  contracts, bids, payments, RFQs, purchase requests,
     *                 shipments, disputes, audit logs.
     *
     * These rows continue to reference a `company_id` that points at a
     * soft-deleted company. Read paths use `withTrashed()` (or accept the
     * orphaned FK gracefully) so historical reports keep working even after
     * the company itself is gone. Force-deletes follow the same rule: users
     * are removed, transactional rows stay.
     *
     * Why this matters:
     *   - Audit & compliance: every payment, contract and dispute must have
     *     an immutable history trail.
     *   - Counter-party fairness: the *other* party of a contract should not
     *     lose their record because we removed our side.
     *   - Reporting: government and admin dashboards roll up totals across
     *     all rows including ones whose company was later deleted.
     */
    protected static function booted(): void
    {
        // The `deleting` event fires before BOTH soft-delete and force-delete.
        //
        // In *both* paths users are SOFT-deleted (never force-deleted), even
        // when the company itself is force-deleted. Why?
        //
        //   - PRs, bids, payments, disputes etc. all carry user FKs
        //     (`buyer_id`, `provider_id`, `raised_by`, `approved_by`...).
        //     Force-deleting the user rows would violate those FK constraints
        //     and we'd lose history.
        //   - Soft-deleting users hides them from every default-scoped query,
        //     so functionally "the users are gone": they can't log in, they
        //     don't show up in listings, but the historical references stay
        //     valid and reports keep working.
        //
        // Either way, contracts/bids/payments/etc. are NEVER touched.
        static::deleting(function (Company $company) {
            // Soft-delete each user. ->each() (not ->delete() on the builder)
            // so any User model boot/observer hooks fire properly.
            $company->users()->each(function (User $user) {
                $user->delete();
            });

            // Detach company⇄category pivot — it's a relationship row, not history.
            DB::table('company_category')->where('company_id', $company->id)->delete();

            // NOTE: We deliberately do NOT touch contracts, bids, payments,
            // RFQs, purchase_requests, shipments, disputes, or audit_logs.
            // Their company_id may now reference a missing row — read paths
            // handle nullable relationships defensively (using `optional()`
            // and `withTrashed()` where appropriate).
        });
    }
}
