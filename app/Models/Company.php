<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\FreeZoneAuthority;
use App\Enums\LegalJurisdiction;
use App\Enums\VerificationLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'name',
        'name_ar',
        'registration_number',
        'tax_number',
        'type',
        'status',
        'verification_level',
        'verified_by',
        'verified_at',
        'sanctions_status',
        'sanctions_screened_at',
        'latest_credit_score',
        'latest_credit_band',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'logo',
        // Authorised signature image + company stamp/seal image. Both
        // are uploaded once on the company profile page and reused on
        // every contract this company signs — the contract sign flow
        // refuses to proceed until both files exist.
        'signature_path',
        'stamp_path',
        'documents',
        'certifications',
        'description',
        // Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction.
        'is_free_zone',
        'free_zone_authority',
        'is_designated_zone',
        'legal_jurisdiction',
        // Approval routing engine — contracts above this AED amount
        // require an internal approver to release them to signature.
        // Null = no threshold (all contracts go straight through).
        'approval_threshold_aed',
        // Notification recipient role filter — JSON array of UserRole
        // values that should receive contract events. Null = legacy
        // "notify everyone in the company" behaviour.
        'notification_recipient_roles',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'status' => CompanyStatus::class,
            'verification_level' => VerificationLevel::class,
            'verified_at' => 'datetime',
            'sanctions_screened_at' => 'datetime',
            'documents' => 'array',
            'certifications' => 'array',
            // Phase 2 (UAE Compliance Roadmap) — PDPL Article 20:
            // tax number (TRN) is a fiscal identifier and personal data
            // when the company is a sole proprietorship. Encrypted at
            // rest via Laravel's AES-256-CBC cast. Plaintext is only
            // available in-process after the cast unwraps it.
            'tax_number' => 'encrypted',
            // Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction.
            // Casts to enums so callers can === compare against the
            // canonical values without dealing with raw strings.
            'is_free_zone'        => 'boolean',
            'is_designated_zone'  => 'boolean',
            'free_zone_authority' => FreeZoneAuthority::class,
            'legal_jurisdiction'  => LegalJurisdiction::class,
            // Notification recipient role filter — JSON list of
            // UserRole values that opt in to contract events.
            'notification_recipient_roles' => 'array',
        ];
    }

    /**
     * Phase 3 (UAE Compliance Roadmap) — convenience helper used by
     * ContractService and the supplier directory: returns the legal
     * jurisdiction the company operates under, falling back to federal
     * for legacy rows where the column is null.
     */
    public function jurisdiction(): LegalJurisdiction
    {
        return $this->legal_jurisdiction ?? LegalJurisdiction::FEDERAL;
    }

    /**
     * Phase 4 (UAE Compliance Roadmap) — In-Country Value certificates.
     * The relationship is loaded by the {@see \App\Services\Procurement\IcvScoringService}
     * during bid evaluation.
     */
    public function icvCertificates(): HasMany
    {
        return $this->hasMany(IcvCertificate::class);
    }

    /**
     * Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening) —
     * compute the company's Peppol Participant Identifier from its UAE
     * tax_number. The Peppol routing layer addresses every party by a
     * scheme-prefixed string of the form `<scheme>:<value>`. The UAE
     * scheme code is 0235 (registered with the Peppol authority for
     * the UAE Federal Tax Authority TRN namespace).
     *
     * This is a derived value, not stored on the column, so it stays
     * in sync automatically when the TRN is updated. Returns null when
     * the company has no TRN — those rows can't be addressed via
     * Peppol and the FTA submission would fail anyway.
     *
     * Used by {@see \App\Services\EInvoice\PintAeMapper} when stamping
     * the EndpointID on AccountingSupplierParty / Customer.
     */
    public function peppolParticipantId(): ?string
    {
        $trn = $this->tax_number;
        if (empty($trn)) {
            return null;
        }
        return '0235:' . $trn;
    }

    /**
     * Convenience: pick the supplier's currently usable ICV score for
     * scoring. Returns the highest score among all verified, non-expired
     * certificates so a company holding both a MoIAT cert and an ADNOC
     * cert can put its best foot forward. Null when no usable cert
     * exists — the scoring service treats this as a 0 score.
     */
    public function latestActiveIcvScore(): ?float
    {
        $cert = $this->icvCertificates()
            ->where('status', IcvCertificate::STATUS_VERIFIED)
            ->where('expires_date', '>=', now()->toDateString())
            ->orderByDesc('score')
            ->first();

        return $cert ? (float) $cert->score : null;
    }

    /**
     * True when this company is in a VAT Designated Zone (Cabinet
     * Decision 59/2017). Drives VAT clause selection on the contract.
     */
    public function isInDesignatedZone(): bool
    {
        return (bool) $this->is_designated_zone;
    }

    /**
     * The company's active "admin needs more info" request. HasOne because
     * we enforce a unique index on company_id in the table — there's only
     * ever one active request per company at a time.
     */
    public function infoRequest(): HasOne
    {
        return $this->hasOne(CompanyInfoRequest::class);
    }

    /** Typed bank details — IBAN / SWIFT / holder name — for payouts. */
    public function bankDetails(): HasOne
    {
        return $this->hasOne(CompanyBankDetail::class);
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

    public function companyDocuments(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * Phase 3.5 (UAE Compliance Roadmap — post-implementation hardening) —
     * trade license validity check used as a gate by ContractService and
     * BidService. A company without a valid (verified, non-expired)
     * trade license is not a legal entity capable of binding itself to
     * a contract under Federal Decree-Law 50/2022 Article 5, and any
     * contract it signs is voidable on application of the counterparty.
     *
     * Returns true when the company holds at least one CompanyDocument
     * of type TRADE_LICENSE that is verified and either has no expiry
     * or has an expiry strictly in the future.
     *
     * Returns false in two cases the gate cares about:
     *   - the company has never uploaded a trade license
     *   - the most recent trade license has been verified but is past
     *     its expires_at date
     *
     * Pending / rejected / expired status rows do NOT count.
     */
    public function hasValidTradeLicense(): bool
    {
        $latest = $this->companyDocuments()
            ->where('type', \App\Enums\DocumentType::TRADE_LICENSE)
            ->where('status', CompanyDocument::STATUS_VERIFIED)
            ->latest('id')
            ->first();

        if (!$latest) {
            return false;
        }

        if ($latest->expires_at === null) {
            // Some jurisdictions issue licenses without an expiry — DIFC
            // perpetual licences, for example. The verified row stands
            // until it's superseded.
            return true;
        }

        return $latest->expires_at->isFuture();
    }

    public function sanctionsScreenings(): HasMany
    {
        return $this->hasMany(SanctionsScreening::class)->latest();
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(BeneficialOwner::class);
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(CompanyInsurance::class);
    }

    public function creditScores(): HasMany
    {
        return $this->hasMany(CreditScore::class)->latest('reported_at');
    }

    /**
     * Phase 8 — at most one ESG questionnaire per company. Resubmissions
     * overwrite the row in place via EsgScoringService::score, so this
     * relation always reflects the latest assessment.
     */
    public function esgQuestionnaire(): HasOne
    {
        return $this->hasOne(EsgQuestionnaire::class);
    }

    /**
     * Phase 8 — annual modern slavery statements (one row per reporting
     * year). Ordered newest-first so the supplier profile and ESG
     * dashboard surface the latest filing without sorting in code.
     */
    public function modernSlaveryStatements(): HasMany
    {
        return $this->hasMany(ModernSlaveryStatement::class)->orderByDesc('reporting_year');
    }

    /**
     * Phase 8 — annual conflict minerals (3TG) declarations. Same
     * newest-first ordering as the modern slavery statements.
     */
    public function conflictMineralsDeclarations(): HasMany
    {
        return $this->hasMany(ConflictMineralsDeclaration::class)->orderByDesc('reporting_year');
    }

    /**
     * Phase 8 — Scope 1/2/3 carbon footprint entries logged against
     * this company. Used by the ESG dashboard's 12-month roll-up. The
     * `entity_id` filter is the polymorphic discriminator since the
     * `carbon_footprints` table holds rows for companies, contracts,
     * AND shipments under the same schema.
     */
    public function carbonFootprints(): HasMany
    {
        return $this->hasMany(CarbonFootprint::class, 'entity_id')
            ->where('entity_type', CarbonFootprint::ENTITY_COMPANY);
    }

    /**
     * True iff the company has at least one verified, non-expired
     * insurance policy on file. Drives the "Insured" badge in the
     * supplier profile + bid card (Phase 2 / Sprint 10 / task 2.15).
     */
    public function isInsured(): bool
    {
        return $this->insurances()
            ->where('status', CompanyInsurance::STATUS_VERIFIED)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::ACTIVE;
    }

    /**
     * True iff the company has uploaded BOTH the authorised signature
     * image AND the company stamp/seal. The contract sign flow uses
     * this to decide whether to show the inline upload modal before
     * routing the user to the actual sign action — see
     * ContractController::show().
     */
    public function hasSignatureAssets(): bool
    {
        return !empty($this->signature_path) && !empty($this->stamp_path);
    }

    /**
     * Public asset URL for the authorised signature image. Returns
     * null when nothing has been uploaded yet so the view can render
     * an empty-state placeholder instead of a broken <img>.
     */
    public function signatureUrl(): ?string
    {
        return $this->signature_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path)
            : null;
    }

    /**
     * Public asset URL for the company stamp/seal image.
     */
    public function stampUrl(): ?string
    {
        return $this->stamp_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->stamp_path)
            : null;
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
