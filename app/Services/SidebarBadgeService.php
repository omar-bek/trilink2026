<?php

namespace App\Services;

use App\Enums\CompanyType;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\ShipmentStatus;
use App\Models\AuditLog;
use App\Models\BeneficialOwner;
use App\Models\Bid;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyInsurance;
use App\Models\CompanySupplier;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\ErpConnector;
use App\Models\EscrowAccount;
use App\Models\EsgQuestionnaire;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\SanctionsScreening;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sidebar badge counts shown on every dashboard page request.
 *
 * Counts are cached per (user, role) for 60 seconds to keep DB load
 * predictable; mutations to the underlying entities should call
 * forgetFor() to expire the cache for the affected company's users.
 *
 * Every visible sidebar item maps to a key here so the blade can render
 * a live count next to each link without per-request DB hits.
 */
class SidebarBadgeService
{
    /** Cache TTL in seconds — short enough to feel "live", long enough to absorb burst loads. */
    public const TTL = 60;

    /**
     * @return array<string,int>
     */
    public function for(?User $user): array
    {
        if (! $user) {
            return $this->empty();
        }

        $role = $user->role?->value ?? 'buyer';

        // Platform admins / government users have no company_id but still need
        // meaningful badges — they see system-wide counts.
        if (! $user->company_id) {
            if (! in_array($role, ['admin', 'government'], true)) {
                return $this->empty();
            }

            $key = "sidebar.badges.global.{$user->id}.{$role}";

            return Cache::remember($key, self::TTL, fn () => $this->computeGlobal($user));
        }

        $key = $this->cacheKey($user->id, $role);

        return Cache::remember($key, self::TTL, fn () => $this->compute($user, $role));
    }

    /** Drop the cached badges for a single user. */
    public function forgetFor(int $userId, string $role): void
    {
        Cache::forget($this->cacheKey($userId, $role));
        Cache::forget("sidebar.badges.global.{$userId}.{$role}");
    }

    /** Drop cached badges for every user in a company (called from observers on entity mutations). */
    public function forgetForCompany(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        User::where('company_id', $companyId)
            ->select(['id', 'role'])
            ->get()
            ->each(fn (User $u) => $this->forgetFor($u->id, $u->role?->value ?? 'buyer'));
    }

    private function cacheKey(int $userId, string $role): string
    {
        return "sidebar.badges.user.{$userId}.{$role}";
    }

    /**
     * Empty default — every key the sidebar expects to read is enumerated
     * here so blade access never trips an undefined index.
     *
     * @return array<string,int>
     */
    private function empty(): array
    {
        return [
            // Procurement
            'purchase-requests'  => 0,
            'rfqs'               => 0,
            'bids'               => 0,
            'contracts'          => 0,
            'catalog'            => 0,
            'suppliers-directory'=> 0,
            'products'           => 0,

            // Operations
            'shipments'          => 0,
            'payments'           => 0,
            'escrow'             => 0,
            'cart'               => 0,
            'disputes'           => 0,
            'esg'                => 0,
            'integrations'       => 0,

            // Management
            'pending-requests'   => 0,
            'company-users'      => 0,
            'branches'           => 0,
            'suppliers'          => 0,
            'documents'          => 0,
            'beneficial-owners'  => 0,
            'insurances'         => 0,

            // Settings
            'api-tokens'         => 0,

            // Admin (system-wide)
            'admin-users'        => 0,
            'admin-companies'    => 0,
            'admin-verification' => 0,
            'admin-categories'   => 0,
            'admin-tax-rates'    => 0,
            'admin-settings'     => 0,
            'admin-audit'        => 0,
        ];
    }

    /**
     * Per-company counts. Suppliers see their own scope, buyers see theirs;
     * each branch is wrapped in its own try so a missing column on one
     * model never blanks out the entire sidebar.
     *
     * @return array<string,int>
     */
    private function compute(User $user, string $role): array
    {
        $badges = $this->empty();
        $cid    = $user->company_id;

        // Supplier-side detection mirrors FormatsForViews::isSupplierSideUser():
        // a user is supplier-side if EITHER their role is a pure supplier
        // role OR their company TYPE is supplier/logistics/clearance/
        // service_provider. The role-only check this method used to do
        // sent every company_manager (and finance/sales) of a supplier
        // company down the buyer branch, where the RFQ count then queried
        // "RFQs OUR company published" and returned 0 because suppliers
        // don't publish RFQs — the badge was silently zero.
        $isSupplierSide = $this->isSupplierSideUser($user, $role);

        // Procurement workflow ────────────────────────────────────────
        //
        // Badge convention: every badge here MUST equal the total number
        // of rows the user will see when they click into the matching
        // index page. Status / freshness filters used to live here, but
        // they made the badge smaller than the index — users would see
        // "RFQs (7)" in the sidebar then click in and find 10 rows on
        // the page, which felt buggy. The actionable-subset counts
        // (e.g. "pending-requests") are surfaced as their own dedicated
        // badges instead.
        $this->safe(function () use (&$badges, $cid, $isSupplierSide) {
            if (! $isSupplierSide) {
                // Buyer side — every PR / RFQ this company OWNS, no
                // status filter, so the badge matches the "all" tab on
                // the index page.
                $badges['purchase-requests'] = PurchaseRequest::where('company_id', $cid)->count();
                // Actionable subset stays separate — this is the "needs
                // attention" pill the manager sees in the sidebar.
                $badges['pending-requests']  = PurchaseRequest::where('company_id', $cid)
                    ->where('status', PurchaseRequestStatus::PENDING_APPROVAL->value)
                    ->count();
                $badges['rfqs'] = Rfq::where('company_id', $cid)->count();
            } else {
                // Supplier side — every OPEN RFQ from another company is
                // a row on the marketplace tab. No created_at filter so
                // the badge matches what supplierIndex() returns.
                $badges['rfqs'] = Rfq::query()
                    ->where('status', RfqStatus::OPEN->value)
                    ->where('company_id', '!=', $cid)
                    ->count();
            }
        });

        $this->safe(function () use (&$badges, $cid, $isSupplierSide, $role) {
            // Bids that the company SUBMITTED vs bids it RECEIVED.
            // Supplier-side users (and sales / sales_manager on the
            // buyer side, who author offers) see their submissions;
            // everyone else gets "received on our RFQs". No status
            // filter — matches the bid index "total" column exactly.
            if ($isSupplierSide || in_array($role, ['sales', 'sales_manager'], true)) {
                $badges['bids'] = Bid::where('company_id', $cid)->count();
            } else {
                $badges['bids'] = Bid::whereHas('rfq', fn ($q) => $q->where('company_id', $cid))->count();
            }
        });

        // Contracts — counted via buyer_company_id OR parties JSON, no
        // status filter so the badge matches every row that will appear
        // on the contracts index page (active, signed, draft, completed,
        // cancelled — everything).
        $this->safe(function () use (&$badges, $cid) {
            $badges['contracts'] = Contract::query()
                ->where(function ($q) use ($cid) {
                    $q->where('buyer_company_id', $cid)
                      ->orWhereJsonContains('parties', ['company_id' => $cid]);
                })
                ->count();
        });

        // Marketplace catalog — number of public/active products.
        $this->safe(function () use (&$badges) {
            $badges['catalog'] = Product::query()->count();
        });

        // Suppliers directory — count of suppliers visible in the directory.
        $this->safe(function () use (&$badges, $cid) {
            $badges['suppliers-directory'] = Company::query()
                ->where('type', 'supplier')
                ->where('status', 'active')
                ->where('id', '!=', $cid)
                ->count();
        });

        // Own products — supplier-side catalog count.
        $this->safe(function () use (&$badges, $cid) {
            $badges['products'] = Product::where('company_id', $cid)->count();
        });

        // Operations ──────────────────────────────────────────────────
        $this->safe(function () use (&$badges, $cid) {
            $badges['shipments'] = Shipment::where('company_id', $cid)
                ->whereIn('status', [
                    ShipmentStatus::IN_PRODUCTION->value,
                    ShipmentStatus::READY_FOR_PICKUP->value,
                    ShipmentStatus::IN_TRANSIT->value,
                    ShipmentStatus::IN_CLEARANCE->value,
                ])->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['payments'] = Payment::where('company_id', $cid)
                ->whereIn('status', [
                    PaymentStatus::PENDING_APPROVAL->value,
                    PaymentStatus::PROCESSING->value,
                ])->count();
        });

        // Escrow accounts — joined via contract.buyer_company_id since the
        // EscrowAccount model has no direct company_id column.
        $this->safe(function () use (&$badges, $cid) {
            $badges['escrow'] = EscrowAccount::query()
                ->where('status', EscrowAccount::STATUS_ACTIVE)
                ->whereHas('contract', fn ($q) => $q->where('buyer_company_id', $cid))
                ->count();
        });

        // Cart — open cart's total line quantity for the user.
        $this->safe(function () use (&$badges, $user) {
            $badges['cart'] = (int) DB::table('cart_items')
                ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
                ->where('carts.user_id', $user->id)
                ->where('carts.status', Cart::STATUS_OPEN)
                ->whereNull('carts.deleted_at')
                ->sum('cart_items.quantity');
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['disputes'] = Dispute::where(function ($q) use ($cid) {
                    $q->where('company_id', $cid)->orWhere('against_company_id', $cid);
                })
                ->whereIn('status', [
                    DisputeStatus::OPEN->value,
                    DisputeStatus::UNDER_REVIEW->value,
                    DisputeStatus::ESCALATED->value,
                ])
                ->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['esg'] = EsgQuestionnaire::where('company_id', $cid)->count();
        });

        // Integrations — webhook endpoints + ERP connectors.
        $this->safe(function () use (&$badges, $cid) {
            $webhooks  = WebhookEndpoint::where('company_id', $cid)->count();
            $erp       = ErpConnector::where('company_id', $cid)->count();
            $badges['integrations'] = $webhooks + $erp;
        });

        // Management ──────────────────────────────────────────────────
        $this->safe(function () use (&$badges, $cid) {
            $badges['company-users'] = User::where('company_id', $cid)->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['branches'] = Branch::where('company_id', $cid)->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['suppliers'] = CompanySupplier::where('company_id', $cid)->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['documents'] = CompanyDocument::where('company_id', $cid)->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['beneficial-owners'] = BeneficialOwner::where('company_id', $cid)->count();
        });

        $this->safe(function () use (&$badges, $cid) {
            $badges['insurances'] = CompanyInsurance::where('company_id', $cid)->count();
        });

        // Settings — API tokens (Sanctum personal access tokens).
        $this->safe(function () use (&$badges, $user) {
            if (Schema::hasTable('personal_access_tokens')) {
                $badges['api-tokens'] = (int) DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', User::class)
                    ->count();
            }
        });

        return $badges;
    }

    /**
     * System-wide counts shown to platform admins / government users who
     * have no company scope. Same shape as compute() so the blade renders
     * identically regardless of role.
     *
     * @return array<string,int>
     */
    private function computeGlobal(User $user): array
    {
        $badges = $this->empty();

        $this->safe(function () use (&$badges) {
            $badges['purchase-requests'] = PurchaseRequest::query()
                ->whereNotIn('status', [PurchaseRequestStatus::REJECTED->value])
                ->count();
            $badges['pending-requests'] = PurchaseRequest::query()
                ->where('status', PurchaseRequestStatus::PENDING_APPROVAL->value)
                ->count();
            $badges['rfqs'] = Rfq::query()->where('status', RfqStatus::OPEN->value)->count();
            $badges['bids'] = Bid::query()->count();
            $badges['contracts'] = Contract::query()
                ->whereIn('status', [
                    ContractStatus::PENDING_SIGNATURES->value,
                    ContractStatus::SIGNED->value,
                    ContractStatus::ACTIVE->value,
                ])->count();
            $badges['catalog'] = Product::query()->count();
            $badges['products'] = Product::query()->count();
            $badges['suppliers-directory'] = Company::where('type', 'supplier')->where('status', 'active')->count();
            $badges['shipments'] = Shipment::query()
                ->whereIn('status', [
                    ShipmentStatus::IN_PRODUCTION->value,
                    ShipmentStatus::READY_FOR_PICKUP->value,
                    ShipmentStatus::IN_TRANSIT->value,
                    ShipmentStatus::IN_CLEARANCE->value,
                ])->count();
            $badges['payments'] = Payment::query()
                ->whereIn('status', [
                    PaymentStatus::PENDING_APPROVAL->value,
                    PaymentStatus::PROCESSING->value,
                ])->count();
            $badges['escrow'] = EscrowAccount::where('status', EscrowAccount::STATUS_ACTIVE)->count();
            $badges['disputes'] = Dispute::query()
                ->whereIn('status', [
                    DisputeStatus::OPEN->value,
                    DisputeStatus::UNDER_REVIEW->value,
                    DisputeStatus::ESCALATED->value,
                ])
                ->count();
            $badges['esg']           = EsgQuestionnaire::query()->count();
            $badges['integrations']  = WebhookEndpoint::query()->count() + ErpConnector::query()->count();
            $badges['company-users'] = User::query()->count();
            $badges['branches']      = Branch::query()->count();
            $badges['suppliers']     = CompanySupplier::query()->count();
            $badges['documents']     = CompanyDocument::query()->count();
            $badges['beneficial-owners'] = BeneficialOwner::query()->count();
            $badges['insurances']    = CompanyInsurance::query()->count();
        });

        $this->safe(function () use (&$badges, $user) {
            if (Schema::hasTable('personal_access_tokens')) {
                $badges['api-tokens'] = (int) DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', User::class)
                    ->count();
            }
        });

        // Admin section — system-wide meta counts only meaningful for admins.
        $this->safe(function () use (&$badges) {
            $badges['admin-users']       = User::query()->count();
            $badges['admin-companies']   = Company::query()->count();
            $badges['admin-verification']= CompanyDocument::where('status', CompanyDocument::STATUS_PENDING)->count()
                                         + SanctionsScreening::query()->where('status', 'review')->count();
            $badges['admin-categories']  = Category::query()->count();
            $badges['admin-tax-rates']   = TaxRate::query()->count();
            $badges['admin-settings']    = Setting::query()->count();
            $badges['admin-audit']       = AuditLog::query()->where('created_at', '>=', now()->subDay())->count();
        });

        return $badges;
    }

    /**
     * Run a closure that may touch a missing table/column without bringing
     * the entire sidebar down. We log the error but never re-throw — the
     * sidebar is chrome and must always render.
     */
    private function safe(\Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Whether the given user should be treated as supplier-side for the
     * purposes of badge counting. Mirrors
     * {@see \App\Http\Controllers\Web\Concerns\FormatsForViews::isSupplierSideUser()}
     * — a user is supplier-side if EITHER their role is a pure supplier
     * role OR their company type is one of the supplier-side types.
     *
     * Kept inline (instead of taking the controller trait as a dependency)
     * because this service is also used by background workers / jobs that
     * have no controller around them.
     */
    private function isSupplierSideUser(User $user, string $role): bool
    {
        // Pure supplier-side roles short-circuit immediately so the
        // company lookup is skipped entirely for the common case.
        if (in_array($role, ['supplier', 'service_provider', 'logistics', 'clearance'], true)) {
            return true;
        }

        if (!$user->company_id) {
            return false;
        }

        // Single-column lookup keeps the query cheap; the parent for() call
        // is already inside Cache::remember so this only fires on cache miss.
        $type = Company::query()
            ->whereKey($user->company_id)
            ->value('type');

        $typeValue = $type instanceof CompanyType ? $type->value : (string) $type;

        return in_array($typeValue, ['supplier', 'service_provider', 'logistics', 'clearance'], true);
    }
}
