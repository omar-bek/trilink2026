<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
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
            'supplier', 'service_provider' => $this->supplierPayload($companyId),
            'logistics'                    => $this->logisticsPayload($companyId),
            'clearance'                    => $this->clearancePayload($companyId),
            'government'                   => $this->governmentPayload(),
            'admin'                        => $this->adminPayload(),
            default                        => $this->buyerPayload($companyId),
        };

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
            ->whereIn('status', ['active', 'signed'])
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
                'progress'       => 65,
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
                'progress'       => 65,
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
        $availableRfqs = Rfq::query()
            ->where('status', RfqStatus::OPEN->value)
            ->when($companyId, fn ($q) => $q->where('company_id', '!=', $companyId))
            ->with('bids')
            ->latest()
            ->limit(3)
            ->get();

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
                Contract::query()->whereIn('status', ['active', 'signed'])
                    ->when($companyId, fn ($q) => $q->whereJsonContains('parties', ['company_id' => $companyId]))
                    ->count(),
                __('contracts.title'),
                'orange',
                'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ),
            $this->stat($successRate . '%', __('supplier.success_rate'), 'green', 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'),
        ];

        $primaryList = [
            'title'          => __('supplier.new_rfqs_available'),
            'subtitle'       => __('supplier.matching_categories'),
            'view_all_route' => 'dashboard.rfqs',
            'items'          => $availableRfqs->map(fn (Rfq $r) => [
                'id'     => '#' . $r->rfq_number,
                'title'  => $r->title,
                'amount' => $this->money((float) $r->budget, $r->currency ?? 'AED'),
                'status' => 'open',
                'meta1'  => $r->bids->count() . ' bids',
                'meta2'  => optional($r->deadline)->format('M j'),
                'href'   => route('dashboard.rfqs.show', ['id' => $r->id]),
            ])->all(),
        ];

        $listLeft = [
            'title'          => __('supplier.my_active_bids'),
            'subtitle'       => 'Submitted and under review',
            'view_all_route' => 'dashboard.bids',
            'items'          => $myActiveBids->map(fn (Bid $b) => [
                'id'             => sprintf('BID-%04d · #%s', $b->id, $b->rfq?->rfq_number ?? '—'),
                'title'          => $b->rfq?->title ?? '—',
                'amount'         => $this->money((float) $b->price, $b->currency ?? 'AED'),
                'progress_label' => ucfirst(str_replace('_', ' ', $b->status?->value ?? 'submitted')),
                'progress'       => $b->status?->value === BidStatus::UNDER_REVIEW->value ? 80 : 40,
                'href'           => route('dashboard.bids.show', ['id' => $b->id]),
            ])->all(),
        ];

        $contracts = Contract::query()
            ->whereIn('status', ['active', 'signed'])
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
                'progress'       => 45,
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
            'title'          => 'Recent Payments',
            'subtitle'       => 'Payments received from buyers',
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
                'progress'       => 65,
                'eta'            => optional($sh->estimated_delivery)->format('M j, Y'),
                'href'           => route('dashboard.shipments.show', ['id' => $sh->id]),
            ])->all(),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function clearancePayload(?int $companyId): array
    {
        // Same general shape as logistics. Bottom section emphasizes customs.
        return $this->logisticsPayload($companyId);
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
            $this->stat(Dispute::where('status', 'resolved')->count(), __('disputes.resolved'), 'green', 'M9 12.75L11.25 15 15 9.75'),
            $this->stat(Company::count(), __('admin.companies'), 'blue', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5'),
            $this->stat(
                $this->shortMoney((float) Contract::where('status', 'active')->sum('total_amount')),
                'Active GMV',
                'orange',
                'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22'
            ),
        ];

        $primaryList = [
            'title'          => __('gov.escalated_disputes'),
            'subtitle'       => 'Pending government resolution',
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
            'title'    => 'Recent Companies',
            'subtitle' => 'Newly registered',
            'view_all_route' => null,
            'items'    => $recentCompanies->map(fn (Company $c) => [
                'id'             => 'CO-' . $c->id,
                'title'          => $c->name,
                'progress_label' => $c->city ?? '—',
                'progress'       => 100,
            ])->all(),
        ];

        $resolvedDisputes = Dispute::where('status', 'resolved')->with(['contract'])->latest()->limit(3)->get();
        $listRight = [
            'title'    => 'Recently Resolved',
            'subtitle' => 'Disputes closed this week',
            'view_all_route' => 'dashboard.disputes',
            'items'    => $resolvedDisputes->map(fn (Dispute $d) => [
                'id'             => 'DIS-' . $d->id,
                'title'          => $d->title,
                'progress_label' => 'Resolved',
                'progress'       => 100,
                'href'           => route('dashboard.disputes.show', ['id' => $d->id]),
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
            'bottomSection' => null,
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
            $this->stat(Company::where('status', 'pending')->count(), __('dashboard.pending_approvals'), 'orange', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'),
            $this->stat(Dispute::whereIn('status', ['open', 'under_review', 'escalated'])->count(), __('disputes.open'), 'red', 'M12 9v3.75'),
        ];

        $recentUsers = User::with('company')->latest()->limit(3)->get();
        $primaryList = [
            'title'          => 'Recent Users',
            'subtitle'       => 'Newly registered accounts',
            'view_all_route' => 'admin.users.index',
            'items'          => $recentUsers->map(fn (User $u) => [
                'id'    => 'U-' . $u->id,
                'title' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'meta1' => $u->email,
                'meta2' => $u->company?->name ?? '—',
                'status' => 'open',
            ])->all(),
        ];

        $pendingCompanies = Company::where('status', 'pending')->latest()->limit(3)->get();
        $listLeft = [
            'title'          => __('admin.companies') . ' — ' . __('dashboard.pending_approvals'),
            'subtitle'       => 'Awaiting verification',
            'view_all_route' => 'admin.companies.index',
            'items'          => $pendingCompanies->map(fn (Company $c) => [
                'id'             => 'CO-' . $c->id,
                'title'          => $c->name,
                'progress_label' => $c->city ?? '—',
                'progress'       => 30,
            ])->all(),
        ];

        $activeContracts = Contract::with('buyerCompany')->where('status', 'active')->latest()->limit(3)->get();
        $listRight = [
            'title'          => __('dashboard.active_contracts'),
            'subtitle'       => 'System-wide',
            'view_all_route' => 'dashboard.contracts',
            'items'          => $activeContracts->map(fn (Contract $c) => [
                'id'             => $c->contract_number,
                'title'          => $c->title,
                'amount'         => $this->money((float) $c->total_amount, $c->currency ?? 'AED'),
                'progress_label' => __('contracts.in_production'),
                'progress'       => 70,
                'href'           => route('dashboard.contracts.show', ['id' => $c->id]),
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
            'bottomSection' => null,
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
