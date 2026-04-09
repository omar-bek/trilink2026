<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\RfqType;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\OnboardingChecklistService;
use App\Support\NotificationFormatter;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Builds the unified dashboard payload for whichever role is logged in.
 *
 * Every role renders the SAME view (`dashboard.shell`), but the data each
 * gets differs: KPI cards, the lead "primary list", two parallel side-by-side
 * lists, and a bottom 3-column section. The shell is intentionally generic so
 * adding new roles only requires writing one private payload builder below.
 */
class DashboardController extends Controller
{
    use FormatsForViews;

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly NotificationFormatter $notificationFormatter,
        private readonly OnboardingChecklistService $onboarding = new OnboardingChecklistService(),
    ) {
    }

    public function index(): View
    {
        $authUser  = auth()->user();
        $companyId = $this->currentCompanyId();
        $role      = $authUser?->role?->value ?? 'buyer';

        $user = [
            'name'    => $authUser?->first_name ?? __('common.guest'),
            'company' => $authUser?->company?->name ?? '',
            'role'    => $role,
        ];

        $payload = match ($role) {
            'supplier', 'service_provider'      => $this->supplierPayload($companyId),
            'logistics'                         => $this->logisticsPayload($companyId),
            'clearance'                         => $this->clearancePayload($companyId),
            'finance', 'finance_manager'        => $this->financePayload($companyId, $role),
            'sales', 'sales_manager'            => $this->salesPayload($companyId),
            'company_manager', 'branch_manager' => $this->managerPayload($companyId, $authUser?->id),
            'government'                        => $this->governmentPayload(),
            'admin'                             => $this->adminPayload(),
            default                             => $this->buyerPayload($companyId),
        };

        // Sprint B.6 — onboarding checklist. Same widget across every
        // role; the service derives the per-role steps internally so
        // the controller stays role-agnostic. The view hides itself
        // when every required step is done.
        $payload['onboarding'] = $this->onboarding->for($authUser);

        return view('dashboard.shell', array_merge(['user' => $user], $payload));
    }

    // =====================================================================
    // BUYER / COMPANY MANAGER
    // =====================================================================

    /**
     * @return array<string, mixed>
     */
    private function buyerPayload(?int $companyId): array
    {
        $kpis = $this->analytics->dashboard($companyId);

        $stats = [
            $this->stat($kpis['purchase_requests'], __('dashboard.purchase_requests_label'), 'purple', 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75'),
            $this->stat($kpis['active_rfqs'], __('rfq.title'), 'blue', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25'),
            $this->stat($kpis['active_contracts'], __('contracts.title'), 'orange', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat(
                PurchaseRequest::query()
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->where('status', PurchaseRequestStatus::SUBMITTED->value)
                    ->count(),
                __('dashboard.pending_approvals'),
                'green',
                'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'
            ),
        ];

        // Active RFQs (primary list)
        $rfqs = Rfq::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [RfqStatus::OPEN->value, RfqStatus::DRAFT->value])
            ->with('bids')
            ->latest()
            ->limit(3)
            ->get();

        $primaryList = [
            'title'          => __('dashboard.active_rfqs'),
            'subtitle'       => __('dashboard.current_procurement'),
            'view_all_route' => 'dashboard.rfqs',
            'items'          => $rfqs->map(fn (Rfq $r) => [
                'id'     => '#' . $r->rfq_number,
                'title'  => $r->title,
                'amount' => $this->money((float) $r->budget, $r->currency ?? 'AED'),
                'status' => $r->status?->value === RfqStatus::OPEN->value ? 'open' : 'draft',
                'meta1'  => $r->bids->count() . ' bids',
                'meta2'  => optional($r->deadline)->format('M j'),
                'href'   => route('dashboard.rfqs.show', ['id' => $r->id]),
            ])->all(),
        ];

        // Active Contracts (left list)
        $contracts = Contract::query()
            ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->latest()
            ->limit(3)
            ->get();

        $listLeft = [
            'title'          => __('dashboard.active_contracts'),
            'subtitle'       => __('dashboard.in_execution'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $contracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Active Shipments (right list)
        $shipments = Shipment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [ShipmentStatus::IN_TRANSIT->value, ShipmentStatus::IN_CLEARANCE->value])
            ->with('contract')
            ->latest()
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('dashboard.active_shipments'),
            'subtitle'       => __('dashboard.in_transit_tracking'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $shipments->map(fn (Shipment $sh) => [
                'id'             => $sh->tracking_number,
                'title'          => $sh->contract?->title ?? '—',
                'progress_label' => __('shipments.in_transit'),
                'progress'       => $sh->realProgress(),
                'eta'            => optional($sh->estimated_delivery)->format('M j, Y'),
                'href'           => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        // Pending Payments (bottom section)
        $payments = Payment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [PaymentStatus::PENDING_APPROVAL->value, PaymentStatus::APPROVED->value])
            ->with(['contract', 'recipientCompany'])
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('dashboard.pending_payments'),
            'subtitle'       => __('dashboard.upcoming_milestones'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $payments->map(fn (Payment $p) => [
                'id'           => $p->contract?->contract_number ?? sprintf('PAY-%04d', $p->id),
                'supplier'     => $p->recipientCompany?->name ?? '—',
                'milestone'    => $p->milestone ?? '—',
                'amount'       => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'date'         => optional($p->created_at)->format('M j, Y'),
                'status'       => $p->status?->value === PaymentStatus::PENDING_APPROVAL->value ? 'due_soon' : 'scheduled',
                'href'         => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('dashboard.create_pr'),
                'route' => 'dashboard.purchase-requests.create',
                'icon'  => 'M12 4.5v15m7.5-7.5h-15',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // SUPPLIER / SERVICE PROVIDER
    // =====================================================================

    /**
     * @return array<string, mixed>
     */
    private function supplierPayload(?int $companyId): array
    {
        // Phase 1 / task 1.8 — "Recommended for You": replaces the old
        // ordered-by-id stub with a real ranking by Rfq::matchScoreFor()
        // against the viewing supplier's company. We over-fetch (top 30
        // open RFQs by recency), score each in PHP using the existing
        // single-source-of-truth method, and keep the top 3 by score.
        $supplierCompany = $companyId
            ? Company::with('categories:id,parent_id')->find($companyId)
            : null;

        // Eager-load `bids` so the meta1 "N bids" formatter below doesn't
        // fire a count() per row (was N+1 — flagged by the audit). The
        // sortByDesc happens in PHP after the matchScoreFor() pass so the
        // bids relation must already be hydrated by then.
        $availableRfqs = Rfq::query()
            ->where('status', RfqStatus::OPEN->value)
            ->when($companyId, fn ($q) => $q->where('company_id', '!=', $companyId))
            ->with(['bids:id,rfq_id', 'category', 'company'])
            ->latest()
            ->limit(30)
            ->get();

        if ($supplierCompany) {
            $availableRfqs = $availableRfqs
                ->map(function (Rfq $r) use ($supplierCompany) {
                    $r->setAttribute('match_score', $r->matchScoreFor($supplierCompany));
                    return $r;
                })
                ->sortByDesc(fn (Rfq $r) => $r->getAttribute('match_score'))
                ->values()
                ->take(3);
        } else {
            $availableRfqs = $availableRfqs->take(3);
        }

        $myActiveBids = Bid::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value])
            ->with('rfq')
            ->latest()
            ->limit(3)
            ->get();

        $totalBids = Bid::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count();
        $wonBids   = Bid::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->where('status', BidStatus::ACCEPTED->value)->count();
        $successRate = $totalBids > 0 ? round(($wonBids / $totalBids) * 100) : 0;

        $stats = [
            $this->stat(Rfq::where('status', RfqStatus::OPEN->value)->count(), __('supplier.available_rfqs'), 'purple', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5'),
            $this->stat($myActiveBids->count(), __('supplier.my_active_bids'), 'blue', 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25'),
            $this->stat(
                Contract::query()->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
                    ->when($companyId, fn ($q) => $q->whereJsonContains('parties', ['company_id' => $companyId]))
                    ->count(),
                __('contracts.title'),
                'orange',
                'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ),
            $this->stat($successRate . '%', __('supplier.success_rate'), 'green', 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'),
        ];

        $primaryList = [
            'title'          => __('supplier.recommended_for_you'),
            'subtitle'       => __('supplier.recommended_subtitle'),
            'view_all_route' => 'dashboard.rfqs',
            'items'          => $availableRfqs->map(function (Rfq $r) {
                $score = $r->getAttribute('match_score');

                return [
                    'id'     => '#' . $r->rfq_number,
                    'title'  => $r->title,
                    'amount' => $this->money((float) $r->budget, $r->currency ?? 'AED'),
                    'status' => 'open',
                    'meta1'  => $r->bids->count() . ' bids',
                    'meta2'  => $score !== null ? $score . '% match' : (optional($r->deadline)->format('M j') ?: ''),
                    'href'   => route('dashboard.rfqs.show', ['id' => $r->id]),
                ];
            })->all(),
        ];

        $listLeft = [
            'title'          => __('supplier.my_active_bids'),
            'subtitle'       => __('supplier.submitted_and_under_review'),
            'view_all_route' => 'dashboard.bids',
            'items'          => $myActiveBids->map(fn (Bid $b) => [
                'id'             => sprintf('BID-%04d · #%s', $b->id, $b->rfq?->rfq_number ?? '—'),
                'title'          => $b->rfq?->title ?? '—',
                'amount'         => $this->money((float) $b->price, $b->currency ?? 'AED'),
                'progress_label' => ucfirst(str_replace('_', ' ', $b->status?->value ?? 'submitted')),
                // Bid lifecycle progress: SUBMITTED is half-way (the buyer
                // has the offer); UNDER_REVIEW means active comparison is
                // happening; ACCEPTED is the deal closed.
                'progress'       => match ($b->status?->value) {
                    BidStatus::DRAFT->value        => 10,
                    BidStatus::SUBMITTED->value    => 50,
                    BidStatus::UNDER_REVIEW->value => 80,
                    BidStatus::ACCEPTED->value     => 100,
                    BidStatus::REJECTED->value     => 0,
                    default                        => 25,
                },
                'href'           => route('dashboard.bids.show', ['id' => $b->id]),
            ])->all(),
        ];

        $contracts = Contract::query()
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->when($companyId, fn ($q) => $q->whereJsonContains('parties', ['company_id' => $companyId]))
            ->latest()
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('contracts.title'),
            'subtitle'       => __('dashboard.in_execution'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $contracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Recent payments received as supplier
        $recentPayments = Payment::query()
            ->when($companyId, fn ($q) => $q->where('recipient_company_id', $companyId))
            ->with(['contract', 'company'])
            ->latest()
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('supplier.recent_payments'),
            'subtitle'       => __('supplier.payments_received_from_buyers'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $recentPayments->map(fn (Payment $p) => [
                'id'        => $p->contract?->contract_number ?? sprintf('PAY-%04d', $p->id),
                'supplier'  => $p->company?->name ?? '—',
                'milestone' => $p->milestone ?? '—',
                'amount'    => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'date'      => optional($p->created_at)->format('M j, Y'),
                'status'    => $p->status?->value === PaymentStatus::COMPLETED->value ? 'scheduled' : 'due_soon',
                'href'      => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('supplier.browse_rfqs'),
                'route' => 'dashboard.rfqs',
                'icon'  => 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // LOGISTICS
    // =====================================================================

    /**
     * @return array<string, mixed>
     */
    private function logisticsPayload(?int $companyId): array
    {
        // Reuse the supplier payload but swap key sections so the operator sees
        // shipments rather than contracts on the right.
        $payload = $this->supplierPayload($companyId);

        $shipments = Shipment::query()
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId))
            ->whereIn('status', [ShipmentStatus::IN_TRANSIT->value, ShipmentStatus::IN_CLEARANCE->value])
            ->with('contract')
            ->latest()
            ->limit(3)
            ->get();

        $payload['listRight'] = [
            'title'          => __('dashboard.active_shipments'),
            'subtitle'       => __('dashboard.in_transit_tracking'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $shipments->map(fn (Shipment $sh) => [
                'id'             => $sh->tracking_number,
                'title'          => $sh->contract?->title ?? '—',
                'progress_label' => __('shipments.in_transit'),
                'progress'       => $sh->realProgress(),
                'eta'            => optional($sh->estimated_delivery)->format('M j, Y'),
                'href'           => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        return $payload;
    }

    // =====================================================================
    // CLEARANCE
    // =====================================================================

    /**
     * Customs clearance operator dashboard. Distinct from logistics: focuses
     * on shipments currently in customs (IN_CLEARANCE), pending customs
     * documents, and recently cleared shipments instead of in-transit moves.
     *
     * @return array<string, mixed>
     */
    private function clearancePayload(?int $companyId): array
    {
        $base = Shipment::query()
            ->when($companyId, fn ($q) => $q->where('logistics_company_id', $companyId));

        $inClearance = (clone $base)
            ->where('status', ShipmentStatus::IN_CLEARANCE->value)
            ->with('contract')
            ->latest()
            ->limit(3)
            ->get();

        $awaitingCustoms = (clone $base)
            ->whereIn('status', [
                ShipmentStatus::IN_TRANSIT->value,
                ShipmentStatus::IN_CLEARANCE->value,
            ])
            ->where(function ($q) {
                $q->whereNull('customs_clearance_status')
                  ->orWhere('customs_clearance_status', 'pending');
            })
            ->count();

        $clearedThisMonth = (clone $base)
            ->whereIn('status', [ShipmentStatus::DELIVERED->value])
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $totalInClearance = (clone $base)
            ->where('status', ShipmentStatus::IN_CLEARANCE->value)
            ->count();

        $stats = [
            $this->stat($totalInClearance, __('clearance.in_clearance'), 'orange', 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'),
            $this->stat($awaitingCustoms, __('clearance.awaiting_customs'), 'red', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71'),
            $this->stat($clearedThisMonth, __('clearance.cleared_month'), 'green', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat(
                (clone $base)->whereIn('status', [
                    ShipmentStatus::IN_TRANSIT->value,
                    ShipmentStatus::IN_CLEARANCE->value,
                    ShipmentStatus::READY_FOR_PICKUP->value,
                ])->count(),
                __('clearance.active_shipments_total'),
                'blue',
                'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'
            ),
        ];

        $primaryList = [
            'title'          => __('clearance.in_clearance_now'),
            'subtitle'       => __('clearance.in_clearance_subtitle'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $inClearance->map(fn (Shipment $sh) => [
                'id'     => $sh->tracking_number,
                'title'  => $sh->contract?->title ?? '—',
                'amount' => $sh->customs_clearance_status
                    ? __('clearance.status_' . $sh->customs_clearance_status)
                    : __('clearance.status_pending'),
                'status' => 'pending',
                'meta1'  => is_array($sh->origin) ? ($sh->origin['country'] ?? '—') : '—',
                'meta2'  => optional($sh->estimated_delivery)->format('M j'),
                'href'   => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        $arriving = (clone $base)
            ->where('status', ShipmentStatus::IN_TRANSIT->value)
            ->whereNotNull('estimated_delivery')
            ->orderBy('estimated_delivery')
            ->with('contract')
            ->limit(3)
            ->get();

        $listLeft = [
            'title'          => __('clearance.arriving_soon'),
            'subtitle'       => __('clearance.arriving_soon_subtitle'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $arriving->map(fn (Shipment $sh) => [
                'id'             => $sh->tracking_number,
                'title'          => $sh->contract?->title ?? '—',
                'progress_label' => __('shipments.in_transit'),
                'progress'       => $sh->realProgress(),
                'eta'            => optional($sh->estimated_delivery)->format('M j, Y'),
                'href'           => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        $recentlyCleared = (clone $base)
            ->where('status', ShipmentStatus::DELIVERED->value)
            ->latest('updated_at')
            ->with('contract')
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('clearance.recently_cleared'),
            'subtitle'       => __('clearance.recently_cleared_subtitle'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $recentlyCleared->map(fn (Shipment $sh) => [
                'id'             => $sh->tracking_number,
                'title'          => $sh->contract?->title ?? '—',
                'progress_label' => __('shipments.delivered'),
                'progress'       => 100,
                'href'           => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        // Bottom section: shipments needing physical pickup right now — the
        // clearance team's "next on dock" view so they don't have to dig.
        $readyForPickup = (clone $base)
            ->where('status', ShipmentStatus::READY_FOR_PICKUP->value)
            ->with('contract')
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('clearance.ready_for_pickup'),
            'subtitle'       => __('clearance.ready_for_pickup_subtitle'),
            'view_all_route' => 'dashboard.shipments',
            'items'          => $readyForPickup->map(fn (Shipment $sh) => [
                'id'        => $sh->tracking_number,
                'supplier'  => $sh->contract?->title ?? '—',
                'milestone' => is_array($sh->destination) ? ($sh->destination['city'] ?? '—') : '—',
                'amount'    => __('shipments.ready_for_pickup'),
                'date'      => optional($sh->updated_at)->format('M j, Y'),
                'status'    => 'scheduled',
                'href'      => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('clearance.review_clearances'),
                'route' => 'dashboard.shipments',
                'icon'  => 'M9 12.75L11.25 15 15 9.75',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // FINANCE / FINANCE MANAGER
    // =====================================================================

    /**
     * Finance dashboard — payments-centric. The finance role only sees +
     * processes payments (split-duty); the finance_manager additionally
     * approves them and runs the escrow workflow. Both share the same shape
     * but the manager sees a "pending approval" list as the primary section
     * while the analyst sees a "ready to process" list.
     *
     * @return array<string, mixed>
     */
    private function financePayload(?int $companyId, string $role): array
    {
        $isManager = $role === 'finance_manager';

        $base = Payment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        // KPIs ------------------------------------------------------------
        $pendingApproval = (clone $base)
            ->where('status', PaymentStatus::PENDING_APPROVAL->value)
            ->count();

        $readyToProcess = (clone $base)
            ->where('status', PaymentStatus::APPROVED->value)
            ->count();

        $processedThisMonth = (clone $base)
            ->whereIn('status', [
                PaymentStatus::PROCESSING->value,
                PaymentStatus::COMPLETED->value,
            ])
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $monthOutflow = (float) (clone $base)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $stats = [
            $this->stat($pendingApproval, __('finance.pending_approval'), 'orange', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat($readyToProcess, __('finance.ready_to_process'), 'blue', 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25'),
            $this->stat($processedThisMonth, __('finance.processed_month'), 'green', 'M9 12.75L11.25 15 15 9.75'),
            $this->stat($this->shortMoney($monthOutflow), __('finance.month_outflow'), 'purple', 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'),
        ];

        // Primary list -- manager sees "needs my approval", analyst sees
        // "ready to process". Both link to the payment show page where
        // their respective action button is rendered conditionally.
        $primaryStatus = $isManager ? PaymentStatus::PENDING_APPROVAL->value : PaymentStatus::APPROVED->value;

        $primaryRows = (clone $base)
            ->where('status', $primaryStatus)
            ->with(['contract', 'recipientCompany'])
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        $primaryList = [
            'title'          => $isManager ? __('finance.awaiting_your_approval') : __('finance.ready_to_process'),
            'subtitle'       => $isManager ? __('finance.approval_subtitle') : __('finance.process_subtitle'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $primaryRows->map(fn (Payment $p) => [
                'id'     => sprintf('PAY-%04d', $p->id),
                'title'  => $p->contract?->title ?? ($p->milestone ?? '—'),
                'amount' => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'status' => $isManager ? 'pending' : 'scheduled',
                'meta1'  => $p->recipientCompany?->name ?? '—',
                'meta2'  => optional($p->created_at)->format('M j'),
                'href'   => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        // Recent completions ---------------------------------------------
        $recentCompleted = (clone $base)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->with(['contract', 'recipientCompany'])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $listLeft = [
            'title'          => __('finance.recently_completed'),
            'subtitle'       => __('finance.recently_completed_subtitle'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $recentCompleted->map(fn (Payment $p) => [
                'id'             => sprintf('PAY-%04d', $p->id),
                'title'          => $p->recipientCompany?->name ?? ($p->contract?->title ?? '—'),
                'amount'         => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'progress_label' => __('payments.completed'),
                'progress'       => 100,
                'href'           => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        // Active contracts whose schedule the finance team must service.
        $activeContracts = Contract::query()
            ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->latest()
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('finance.active_obligations'),
            'subtitle'       => __('finance.active_obligations_subtitle'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $activeContracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Bottom section: rejected / failed payments needing attention.
        $needsAttention = (clone $base)
            ->whereIn('status', [PaymentStatus::REJECTED->value, PaymentStatus::FAILED->value])
            ->with(['contract', 'recipientCompany'])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('finance.needs_attention'),
            'subtitle'       => __('finance.needs_attention_subtitle'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $needsAttention->map(fn (Payment $p) => [
                'id'        => sprintf('PAY-%04d', $p->id),
                'supplier'  => $p->recipientCompany?->name ?? '—',
                'milestone' => $p->milestone ?? ($p->contract?->title ?? '—'),
                'amount'    => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'date'      => optional($p->updated_at)->format('M j, Y'),
                'status'    => 'urgent',
                'href'      => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('finance.go_to_payments'),
                'route' => 'dashboard.payments',
                'icon'  => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // SALES / SALES MANAGER
    // =====================================================================

    /**
     * Sales dashboard — focused on outbound SALES_OFFER RFQs (the company is
     * advertising inventory) and inbound buyer bids on those offers. The
     * inverse of the buyer payload: the sales team is the seller, bidders
     * are the buyers.
     *
     * @return array<string, mixed>
     */
    private function salesPayload(?int $companyId): array
    {
        $myOffers = Rfq::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('type', RfqType::SALES_OFFER->value)
            ->with('bids')
            ->latest()
            ->limit(3)
            ->get();

        $offerCount = Rfq::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('type', RfqType::SALES_OFFER->value)
            ->where('status', RfqStatus::OPEN->value)
            ->count();

        $myOfferIds = Rfq::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('type', RfqType::SALES_OFFER->value)
            ->pluck('id');

        $inboundBidsCount = Bid::query()
            ->whereIn('rfq_id', $myOfferIds)
            ->whereIn('status', [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value])
            ->count();

        $closedThisMonth = Bid::query()
            ->whereIn('rfq_id', $myOfferIds)
            ->where('status', BidStatus::ACCEPTED->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $monthRevenue = (float) Bid::query()
            ->whereIn('rfq_id', $myOfferIds)
            ->where('status', BidStatus::ACCEPTED->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->sum('price');

        $stats = [
            $this->stat($offerCount, __('sales.open_offers'), 'purple', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25'),
            $this->stat($inboundBidsCount, __('sales.inbound_bids'), 'blue', 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25'),
            $this->stat($closedThisMonth, __('sales.closed_month'), 'green', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat($this->shortMoney($monthRevenue), __('sales.month_revenue'), 'orange', 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'),
        ];

        $primaryList = [
            'title'          => __('sales.my_sales_offers'),
            'subtitle'       => __('sales.my_sales_offers_subtitle'),
            'view_all_route' => 'dashboard.rfqs',
            'items'          => $myOffers->map(fn (Rfq $r) => [
                'id'     => '#' . $r->rfq_number,
                'title'  => $r->title,
                'amount' => $this->money((float) $r->budget, $r->currency ?? 'AED'),
                'status' => $r->status?->value === RfqStatus::OPEN->value ? 'open' : 'draft',
                'meta1'  => $r->bids->count() . ' ' . __('sales.offers_received'),
                'meta2'  => optional($r->deadline)->format('M j'),
                'href'   => route('dashboard.rfqs.show', ['id' => $r->id]),
            ])->all(),
        ];

        $inboundBids = Bid::query()
            ->whereIn('rfq_id', $myOfferIds)
            ->whereIn('status', [BidStatus::SUBMITTED->value, BidStatus::UNDER_REVIEW->value])
            ->with(['rfq', 'company'])
            ->latest()
            ->limit(3)
            ->get();

        $listLeft = [
            'title'          => __('sales.inbound_bids'),
            'subtitle'       => __('sales.inbound_bids_subtitle'),
            'view_all_route' => 'dashboard.bids',
            'items'          => $inboundBids->map(fn (Bid $b) => [
                'id'             => sprintf('BID-%04d · #%s', $b->id, $b->rfq?->rfq_number ?? '—'),
                'title'          => $b->company?->name ?? ($b->rfq?->title ?? '—'),
                'amount'         => $this->money((float) $b->price, $b->currency ?? 'AED'),
                'progress_label' => ucfirst(str_replace('_', ' ', $b->status?->value ?? 'submitted')),
                'progress'       => match ($b->status?->value) {
                    BidStatus::DRAFT->value        => 10,
                    BidStatus::SUBMITTED->value    => 50,
                    BidStatus::UNDER_REVIEW->value => 80,
                    BidStatus::ACCEPTED->value     => 100,
                    default                        => 25,
                },
                'href'           => route('dashboard.bids.show', ['id' => $b->id]),
            ])->all(),
        ];

        // Active sales contracts — the company is the seller party here, so
        // it lives inside the parties JSON column rather than the buyer FK.
        $salesContracts = Contract::query()
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->when($companyId, fn ($q) => $q->whereJsonContains('parties', ['company_id' => $companyId]))
            ->latest()
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('sales.active_sales_contracts'),
            'subtitle'       => __('sales.active_sales_contracts_subtitle'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $salesContracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Bottom section: bids the sales team has just won this period — the
        // wins reel that turns the dashboard into a leaderboard.
        $recentWins = Bid::query()
            ->whereIn('rfq_id', $myOfferIds)
            ->where('status', BidStatus::ACCEPTED->value)
            ->with(['rfq', 'company'])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('sales.recent_wins'),
            'subtitle'       => __('sales.recent_wins_subtitle'),
            'view_all_route' => 'dashboard.bids',
            'items'          => $recentWins->map(fn (Bid $b) => [
                'id'        => sprintf('BID-%04d', $b->id),
                'supplier'  => $b->company?->name ?? '—',
                'milestone' => $b->rfq?->title ?? '—',
                'amount'    => $this->money((float) $b->price, $b->currency ?? 'AED'),
                'date'      => optional($b->updated_at)->format('M j, Y'),
                'status'    => 'scheduled',
                'href'      => route('dashboard.bids.show', ['id' => $b->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                // No dedicated "create sales offer" form yet — sales reps
                // currently use the unified RFQ index to seed offers, same
                // as the supplier role does.
                'label' => __('sales.browse_offers'),
                'route' => 'dashboard.rfqs',
                'icon'  => 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // COMPANY MANAGER / BRANCH MANAGER
    // =====================================================================

    /**
     * Manager-level dashboard — sees the team's pending approvals (PRs and
     * payments), team activity across all roles, and the company-wide
     * financial obligation snapshot. Branch manager is the same shape but
     * naturally scoped by the company_id (branch scoping happens through
     * model-level relationships, not here).
     *
     * @return array<string, mixed>
     */
    private function managerPayload(?int $companyId, ?int $userId): array
    {
        $prBase = PurchaseRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $pendingPRs = (clone $prBase)
            ->where('status', PurchaseRequestStatus::SUBMITTED->value)
            ->with('buyer')
            ->latest()
            ->limit(3)
            ->get();

        $pendingPRCount = (clone $prBase)
            ->where('status', PurchaseRequestStatus::SUBMITTED->value)
            ->count();

        $pendingPaymentsCount = Payment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', PaymentStatus::PENDING_APPROVAL->value)
            ->count();

        $teamSize = User::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $monthGmv = (float) Contract::query()
            ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $stats = [
            $this->stat($pendingPRCount, __('manager.prs_awaiting_approval'), 'orange', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat($pendingPaymentsCount, __('manager.payments_awaiting_approval'), 'red', 'M2.25 18.75a60.07 60.07 0 0115.797 2.101'),
            $this->stat($teamSize, __('manager.team_size'), 'blue', 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952'),
            $this->stat($this->shortMoney($monthGmv), __('manager.month_gmv'), 'green', 'M2.25 18L9 11.25l4.306 4.307'),
        ];

        $primaryList = [
            'title'          => __('manager.prs_awaiting_approval'),
            'subtitle'       => __('manager.prs_subtitle'),
            'view_all_route' => 'dashboard.purchase-requests',
            'items'          => $pendingPRs->map(fn (PurchaseRequest $pr) => [
                'id'     => sprintf('PR-%04d', $pr->id),
                'title'  => $pr->title,
                'amount' => $this->money((float) ($pr->budget ?? 0), $pr->currency ?? 'AED'),
                'status' => 'pending',
                'meta1'  => trim(($pr->buyer?->first_name ?? '') . ' ' . ($pr->buyer?->last_name ?? '')) ?: '—',
                'meta2'  => optional($pr->created_at)->format('M j'),
                'href'   => route('dashboard.purchase-requests.show', ['id' => $pr->id]),
            ])->all(),
        ];

        $pendingPayments = Payment::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', PaymentStatus::PENDING_APPROVAL->value)
            ->with(['contract', 'recipientCompany'])
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        $listLeft = [
            'title'          => __('manager.payments_awaiting_approval'),
            'subtitle'       => __('manager.payments_subtitle'),
            'view_all_route' => 'dashboard.payments',
            'items'          => $pendingPayments->map(fn (Payment $p) => [
                'id'             => sprintf('PAY-%04d', $p->id),
                'title'          => $p->recipientCompany?->name ?? ($p->contract?->title ?? '—'),
                'amount'         => $this->money((float) $p->total_amount, $p->currency ?? 'AED'),
                'progress_label' => __('payments.pending_approval'),
                'progress'       => 25,
                'href'           => route('dashboard.payments.show', ['id' => $p->id]),
            ])->all(),
        ];

        $activeContracts = Contract::query()
            ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->latest()
            ->limit(3)
            ->get();

        $listRight = [
            'title'          => __('dashboard.active_contracts'),
            'subtitle'       => __('dashboard.in_execution'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $activeContracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Bottom section: most recent team members so the manager has a
        // pulse on who joined last and what role they hold. Uses the same
        // 3-card grid as buyer "pending payments".
        $latestTeammates = User::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('manager.team_recent'),
            'subtitle'       => __('manager.team_recent_subtitle'),
            'view_all_route' => null,
            'items'          => $latestTeammates->map(fn (User $u) => [
                'id'        => 'U-' . $u->id,
                'supplier'  => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: '—',
                'milestone' => __('role.' . ($u->role?->value ?? 'buyer')),
                'amount'    => $u->email ?? '—',
                'date'      => optional($u->created_at)->format('M j, Y'),
                'status'    => 'open',
                'status_label' => __('users.active'),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('manager.review_approvals'),
                'route' => 'dashboard.purchase-requests',
                'icon'  => 'M9 12.75L11.25 15 15 9.75',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications($companyId),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // GOVERNMENT
    // =====================================================================

    /**
     * @return array<string, mixed>
     */
    private function governmentPayload(): array
    {
        $escalated = Dispute::with(['contract', 'company', 'againstCompany'])
            ->where('escalated_to_government', true)
            ->whereNotIn('status', ['resolved'])
            ->latest()
            ->limit(3)
            ->get();

        $stats = [
            $this->stat(
                Dispute::where('escalated_to_government', true)->whereNotIn('status', ['resolved'])->count(),
                __('gov.escalated_disputes'),
                'red',
                'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z'
            ),
            $this->stat(Dispute::where('status', DisputeStatus::RESOLVED->value)->count(), __('disputes.resolved'), 'green', 'M9 12.75L11.25 15 15 9.75'),
            $this->stat(Company::count(), __('admin.companies'), 'blue', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5'),
            $this->stat(
                $this->shortMoney((float) Contract::where('status', ContractStatus::ACTIVE->value)->sum('total_amount')),
                __('gov.active_gmv'),
                'orange',
                'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'
            ),
        ];

        $primaryList = [
            'title'          => __('gov.escalated_disputes'),
            'subtitle'       => __('gov.pending_resolution'),
            'view_all_route' => 'dashboard.disputes',
            'items'          => $escalated->map(fn (Dispute $d) => [
                'id'     => 'DIS-' . $d->id,
                'title'  => $d->title,
                'amount' => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
                'status' => 'urgent',
                'meta1'  => ($d->company?->name ?? '—') . ' vs ' . ($d->againstCompany?->name ?? '—'),
                'meta2'  => optional($d->created_at)->format('M j'),
                'href'   => route('dashboard.disputes.show', ['id' => $d->id]),
            ])->all(),
        ];

        $recentCompanies = Company::latest()->limit(3)->get();
        $listLeft = [
            'title'    => __('gov.recent_companies'),
            'subtitle' => __('gov.newly_registered'),
            'view_all_route' => null,
            'items'    => $recentCompanies->map(fn (Company $c) => [
                'id'             => 'CO-' . $c->id,
                'title'          => $c->name,
                'progress_label' => $c->city ?? '—',
                'progress'       => 100,
            ])->all(),
        ];

        $resolvedDisputes = Dispute::where('status', DisputeStatus::RESOLVED->value)->with(['contract'])->latest()->limit(3)->get();
        $listRight = [
            'title'    => __('gov.recently_resolved'),
            'subtitle' => __('gov.disputes_closed_this_week'),
            'view_all_route' => 'dashboard.disputes',
            'items'    => $resolvedDisputes->map(fn (Dispute $d) => [
                'id'             => 'DIS-' . $d->id,
                'title'          => $d->title,
                'progress_label' => __('disputes.resolved'),
                'progress'       => 100,
                'href'           => route('dashboard.disputes.show', ['id' => $d->id]),
            ])->all(),
        ];

        // Bottom section: highest-value contracts in the system right now.
        // Government oversight wants to see where the money is moving.
        $topContracts = Contract::with('buyerCompany')
            ->whereIn('status', [ContractStatus::ACTIVE->value, ContractStatus::SIGNED->value])
            ->orderByDesc('total_amount')
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('gov.top_active_contracts'),
            'subtitle'       => __('gov.top_active_contracts_subtitle'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $topContracts->map(fn (Contract $c) => [
                'id'        => $c->contract_number,
                'supplier'  => $c->buyerCompany?->name ?? '—',
                'milestone' => $c->title,
                'amount'    => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'date'      => optional($c->created_at)->format('M j, Y'),
                'status'    => 'scheduled',
                'href'      => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('dashboard.review_disputes'),
                'route' => 'dashboard.disputes',
                'icon'  => 'M9 12.75L11.25 15 15 9.75',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications(null),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // ADMIN
    // =====================================================================

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(): array
    {
        $stats = [
            $this->stat(User::count(), __('admin.users'), 'blue', 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952'),
            $this->stat(Company::count(), __('admin.companies'), 'purple', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18'),
            $this->stat(Company::where('status', CompanyStatus::PENDING->value)->count(), __('dashboard.pending_approvals'), 'orange', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat(Dispute::whereIn('status', ['open', 'under_review', 'escalated'])->count(), __('disputes.open'), 'red', 'M12 9v3.75'),
        ];

        $recentUsers = User::with('company')->latest()->limit(3)->get();
        $primaryList = [
            'title'          => __('admin.recent_users'),
            'subtitle'       => __('admin.newly_registered_accounts'),
            'view_all_route' => 'admin.users.index',
            'items'          => $recentUsers->map(fn (User $u) => [
                'id'    => 'U-' . $u->id,
                'title' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'meta1' => $u->email,
                'meta2' => $u->company?->name ?? '—',
                'status' => 'open',
            ])->all(),
        ];

        $pendingCompanies = Company::where('status', CompanyStatus::PENDING->value)->latest()->limit(3)->get();
        $listLeft = [
            'title'          => __('admin.companies') . ' — ' . __('dashboard.pending_approvals'),
            'subtitle'       => __('admin.awaiting_verification'),
            'view_all_route' => 'admin.companies.index',
            'items'          => $pendingCompanies->map(fn (Company $c) => [
                'id'             => 'CO-' . $c->id,
                'title'          => $c->name,
                'progress_label' => $c->city ?? '—',
                // Pending companies sit at 30% in the verification funnel
                // (registered, awaiting KYB review).
                'progress'       => 30,
            ])->all(),
        ];

        $activeContracts = Contract::with('buyerCompany')->where('status', 'active')->latest()->limit(3)->get();
        $listRight = [
            'title'          => __('dashboard.active_contracts'),
            'subtitle'       => __('admin.system_wide'),
            'view_all_route' => 'dashboard.contracts',
            'items'          => $activeContracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => $c->realProgress(),
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
            ])->all(),
        ];

        // Bottom section: open disputes anywhere on the platform — gives the
        // admin a one-click route into the conflicts queue without leaving
        // the dashboard.
        $openDisputes = Dispute::with(['contract', 'company', 'againstCompany'])
            ->whereIn('status', [DisputeStatus::OPEN->value, DisputeStatus::UNDER_REVIEW->value, DisputeStatus::ESCALATED->value])
            ->latest()
            ->limit(3)
            ->get();

        $bottomSection = [
            'title'          => __('disputes.open'),
            'subtitle'       => __('admin.system_wide'),
            'view_all_route' => 'dashboard.disputes',
            'items'          => $openDisputes->map(fn (Dispute $d) => [
                'id'        => 'DIS-' . $d->id,
                'supplier'  => $d->company?->name ?? '—',
                'milestone' => $d->title,
                'amount'    => $this->money((float) ($d->contract?->total_amount ?? 0), $d->contract?->currency ?? 'AED'),
                'date'      => optional($d->created_at)->format('M j, Y'),
                'status'    => 'urgent',
                'href'      => route('dashboard.disputes.show', ['id' => $d->id]),
            ])->all(),
        ];

        return [
            'headerAction'  => [
                'label' => __('dashboard.system_settings'),
                'route' => 'admin.settings.index',
                'icon'  => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593',
            ],
            'stats'         => $stats,
            'primaryList'   => $primaryList,
            'notifications' => $this->buildNotifications(null),
            'listLeft'      => $listLeft,
            'listRight'     => $listRight,
            'bottomSection' => $bottomSection,
        ];
    }

    // =====================================================================
    // SHARED HELPERS
    // =====================================================================

    /**
     * @return array{value:int|string,label:string,color:string,icon:string}
     */
    private function stat($value, string $label, string $color, string $icon): array
    {
        return ['value' => $value, 'label' => $label, 'color' => $color, 'icon' => $icon];
    }

    /**
     * Read the user's real notifications from the `notifications` table and
     * format them via NotificationFormatter. Icons, colors, and links are
     * resolved from each notification's stored `data.entity_type`.
     *
     * `count` is the unread count (used by the bell badge); `items` are the 5
     * newest notifications regardless of read state (so the dropdown is never
     * empty for active users).
     *
     * @return array{count:int, items: array<int, array<string, mixed>>}
     */
    private function buildNotifications(?int $companyId): array
    {
        unset($companyId); // Notifications belong to the user, not the company.

        $user = auth()->user();
        if (!$user) {
            return ['count' => 0, 'items' => []];
        }

        $unreadCount = $user->unreadNotifications()->count();
        $latest      = $user->notifications()->latest()->limit(5)->get();

        return [
            'count' => $unreadCount,
            'items' => $this->notificationFormatter->formatMany($latest),
        ];
    }

    private function shortMoney(float $value, string $currency = 'AED'): string
    {
        if ($value >= 1_000_000) {
            return $currency . ' ' . round($value / 1_000_000, 1) . 'M';
        }
        if ($value >= 1_000) {
            return $currency . ' ' . round($value / 1_000) . 'K';
        }

        return $currency . ' ' . number_format($value);
    }
}
