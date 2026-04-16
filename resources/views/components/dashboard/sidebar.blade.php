@props(['active' => 'dashboard'])

@php
// Authenticated user + role (string).
$authUser = auth()->user();
$role = $authUser?->role?->value ?? 'buyer';

// Sidebar badge counts come from a cached service (60s TTL per user) so
// every dashboard request doesn't pay the cost of a dozen COUNT queries.
// See App\Services\SidebarBadgeService for the underlying queries and
// the forgetForCompany() hook used by entity observers to invalidate.
$badges = app(\App\Services\SidebarBadgeService::class)->for($authUser);

// Color scheme for the role badge — each role gets a distinct accent.
$roleBadgeColors = [
    'buyer'            => ['ring' => '#4f7cff', 'text' => '#4f7cff', 'bg' => 'rgba(79,124,255,0.08)'],
    'company_manager'  => ['ring' => '#4f7cff', 'text' => '#4f7cff', 'bg' => 'rgba(79,124,255,0.08)'],
    'supplier'         => ['ring' => '#00d9b5', 'text' => '#00d9b5', 'bg' => 'rgba(0,217,181,0.08)'],
    'service_provider' => ['ring' => '#00d9b5', 'text' => '#00d9b5', 'bg' => 'rgba(0,217,181,0.08)'],
    'logistics'        => ['ring' => '#8B5CF6', 'text' => '#8B5CF6', 'bg' => 'rgba(139,92,246,0.08)'],
    'clearance'        => ['ring' => '#ffb020', 'text' => '#ffb020', 'bg' => 'rgba(255,176,32,0.08)'],
    'government'       => ['ring' => '#ff4d7f', 'text' => '#ff4d7f', 'bg' => 'rgba(255,77,127,0.08)'],
    'admin'            => ['ring' => '#ff4d7f', 'text' => '#ff4d7f', 'bg' => 'rgba(255,77,127,0.08)'],
];
$roleBadge = $roleBadgeColors[$role] ?? $roleBadgeColors['buyer'];

// Each menu item declares the permission key it requires. The item is hidden
// unless the authenticated user actually holds that permission (checked via
// User::hasPermission, which honours per-user allowlists set by the company
// manager). `null` perm = always visible (e.g. Dashboard for everyone).
// `roles` is kept as a coarse role gate for sections like Government console
// that aren't part of the per-user catalog.
//
// `badge` keys correspond to the array returned by SidebarBadgeService and
// are rendered as a small pill at the end of the row.
$nav = [
    'main' => [
        ['key' => 'dashboard', 'label' => __('nav.dashboard'), 'route' => 'dashboard',
         'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
         'perm' => null, 'roles' => ['*']],
    ],
    'procurement' => [
        'label' => __('nav.procurement'),
        'items' => [
            ['key' => 'purchase-requests', 'label' => __('nav.purchase_requests'), 'route' => 'dashboard.purchase-requests',
             'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272',
             'perm' => 'pr.view', 'badge' => 'purchase-requests'],
            ['key' => 'rfqs', 'label' => __('nav.rfqs'), 'route' => 'dashboard.rfqs',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => 'rfq.view', 'badge' => 'rfqs'],
            ['key' => 'bids', 'label' => __('nav.bids'), 'route' => 'dashboard.bids',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => 'bid.view', 'badge' => 'bids'],
            ['key' => 'contracts', 'label' => __('nav.contracts'), 'route' => 'dashboard.contracts',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => 'contract.view', 'badge' => 'contracts'],
            ['key' => 'catalog', 'label' => __('nav.marketplace'), 'route' => 'dashboard.catalog.browse',
             'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
             'perm' => 'rfq.view', 'badge' => 'catalog'],
            // Phase 1 / task 1.1 — buyer-facing Supplier Directory
            ['key' => 'suppliers-directory', 'label' => __('directory.title'), 'route' => 'dashboard.suppliers.directory',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => null, 'badge' => 'suppliers-directory'],
            ['key' => 'products', 'label' => __('nav.my_catalog'), 'route' => 'dashboard.products.index',
             'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
             'perm' => 'rfq.view', 'badge' => 'products'],
        ],
    ],
    'operations' => [
        'label' => __('nav.operations'),
        'items' => [
            ['key' => 'shipments', 'label' => __('nav.shipment_tracking'), 'route' => 'dashboard.shipments',
             'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
             'perm' => 'shipment.view', 'badge' => 'shipments'],
            ['key' => 'shipping-quotes', 'label' => __('nav.shipping_quotes'), 'route' => 'dashboard.shipping.quotes',
             'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9-1.5h12.75m0 0V8.25c0-.414-.336-.75-.75-.75H6.75a.75.75 0 00-.75.75v9m12 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0M21 12.75H18M3 12.75h12.75',
             'perm' => 'shipment.view'],
            ['key' => 'payments', 'label' => __('nav.payment_management'), 'route' => 'dashboard.payments',
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
             'perm' => 'payment.view', 'badge' => 'payments'],
            // Phase 3 — Trade Finance MVP. Buyers + suppliers can both
            // see their escrow custody dashboard; the per-account actions
            // remain buyer-only inside EscrowController.
            ['key' => 'escrow', 'label' => __('nav.escrow'), 'route' => 'dashboard.escrow.index',
             'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
             'perm' => 'escrow.view', 'badge' => 'escrow'],
            // Phase 4 — Cart entry. Buyer-only (cart.use is in the buyer
            // role default).
            ['key' => 'cart', 'label' => __('nav.cart'), 'route' => 'dashboard.cart.index',
             'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
             'perm' => 'cart.use', 'badge' => 'cart'],
            // Phase 5 — AI Copilot is the primary AI surface and the
            // "always discoverable" entry. OCR + risk + negotiation
            // assistants surface contextually inside their parent pages.
            ['key' => 'ai-copilot', 'label' => __('nav.ai_copilot'), 'route' => 'dashboard.ai.copilot',
             'icon' => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.091zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z',
             'perm' => 'ai.use'],
            ['key' => 'ai-ocr', 'label' => __('nav.ai_ocr'), 'route' => 'dashboard.ai.ocr',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => 'ai.use'],
            ['key' => 'disputes', 'label' => __('nav.disputes'), 'route' => 'dashboard.disputes',
             'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
             'perm' => 'dispute.view', 'badge' => 'disputes'],
            // Phase 8 — ESG dashboard. Read-only for buyers/suppliers,
            // edit-gated by esg.manage inside the controller.
            ['key' => 'esg', 'label' => __('nav.esg'), 'route' => 'dashboard.esg.index',
             'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
             'perm' => 'esg.view', 'badge' => 'esg'],
            // Phase 7 — Integrations (webhooks + ERP connectors).
            // Manager-only because it touches credentials.
            ['key' => 'integrations', 'label' => __('nav.integrations'), 'route' => 'dashboard.integrations.index',
             'icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244',
             'perm' => 'integrations.manage', 'badge' => 'integrations'],
        ],
    ],
    'management' => [
        'label' => __('nav.management'),
        'items' => [
            // Pending Requests: reuses the PR index with a pre-applied status filter.
            // Visible only to users who can approve PRs (company managers + admins).
            ['key' => 'pending-requests', 'label' => __('nav.pending_requests'),
             'route' => 'dashboard.purchase-requests', 'route_query' => ['status' => 'pending_approval'],
             'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
             'perm' => 'pr.approve', 'badge' => 'pending-requests'],
            // Unified Company Profile — manager-facing entry. Shows
            // identity, documents, insurances, ICV, banking, beneficial
            // owners, branches and team in one place. Permission gate
            // is team.view so every company-attached user can see it.
            // Hidden from admin/government because admins reach the
            // SAME profile via /dashboard/admin/companies/{id} and
            // government users have no company tenant of their own.
            ['key' => 'company-profile', 'label' => __('nav.company_profile'), 'route' => 'dashboard.company.profile',
             'icon' => 'M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18',
             'perm' => 'team.view', 'except_roles' => ['admin', 'government']],
            ['key' => 'company-users', 'label' => __('nav.team_management'), 'route' => 'company.users',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => 'team.view', 'badge' => 'company-users'],
            ['key' => 'branches', 'label' => __('nav.branches'), 'route' => 'dashboard.branches.index',
             'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'branches'],
            ['key' => 'suppliers', 'label' => __('nav.approved_suppliers'), 'route' => 'dashboard.suppliers.index',
             'icon' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'suppliers'],
            ['key' => 'documents', 'label' => __('nav.documents'), 'route' => 'dashboard.documents.index',
             'icon' => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'documents'],
            // Phase 2 / Sprint 8 / task 2.7 — beneficial owners disclosure.
            ['key' => 'beneficial-owners', 'label' => __('nav.beneficial_owners'), 'route' => 'dashboard.beneficial-owners.index',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'beneficial-owners'],
            // Phase 2 / Sprint 10 / task 2.14 — insurance vault.
            ['key' => 'insurances', 'label' => __('nav.insurances'), 'route' => 'dashboard.insurances.index',
             'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'insurances'],
            // Phase 4 (UAE Compliance Roadmap) — In-Country Value certificates.
            // Supplier-side self-service for uploading MoIAT/ADNOC ICV
            // certificates. The score drives composite bid evaluation
            // for any RFQ that opts into ICV weighting.
            ['key' => 'icv-certificates', 'label' => __('nav.icv_certificates'), 'route' => 'dashboard.icv-certificates.index',
             'icon' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'icv-certificates'],
            ['key' => 'gov', 'label' => __('gov.title'), 'route' => 'gov.index',
             'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-contracts', 'label' => __('gov.contracts_title'), 'route' => 'gov.contracts',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-payments', 'label' => __('gov.payments_title'), 'route' => 'gov.payments',
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-competition', 'label' => __('gov.competition_title'), 'route' => 'gov.competition',
             'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-disputes', 'label' => __('gov.disputes_title'), 'route' => 'gov.disputes',
             'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-icv', 'label' => __('gov.icv_title'), 'route' => 'gov.icv-report',
             'icon' => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-esg', 'label' => __('gov.esg_title'), 'route' => 'gov.esg-report',
             'icon' => 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-sanctions', 'label' => __('gov.sanctions_title'), 'route' => 'gov.sanctions-report',
             'icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-sme', 'label' => __('gov.sme_title'), 'route' => 'gov.sme-report',
             'icon' => 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35',
             'perm' => null, 'roles' => ['government', 'admin']],
            ['key' => 'gov-collusion', 'label' => __('gov.collusion_title'), 'route' => 'gov.collusion-report',
             'icon' => 'M12 9v3.75m0 0h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z',
             'perm' => null, 'roles' => ['government', 'admin']],
        ],
    ],
    'analytics' => [
        'label' => __('nav.insights'),
        'items' => [
            ['key' => 'analytics-spend', 'label' => __('nav.spend_analytics'), 'route' => 'dashboard.analytics.spend',
             'icon' => 'M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941',
             'perm' => 'reports.view'],
            ['key' => 'performance', 'label' => __('nav.analytics'), 'route' => 'performance.index',
             'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
             'perm' => 'reports.view'],
        ],
    ],
    'settings' => [
        'label' => __('nav.settings'),
        'items' => [
            ['key' => 'settings', 'label' => __('user.settings'), 'route' => 'settings.index',
             'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.241.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z',
             'perm' => null],
            ['key' => 'api-tokens', 'label' => __('nav.api_tokens'), 'route' => 'dashboard.api-tokens.index',
             'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
             'perm' => null, 'roles' => ['company_manager'], 'badge' => 'api-tokens'],
            // Phase 2 (UAE Compliance Roadmap) — PDPL self-service hub.
            // Open to every authenticated user; no permission gate
            // because the data subject is always entitled to act on
            // their own personal data record (PDPL Articles 13-16).
            ['key' => 'privacy', 'label' => __('nav.privacy'), 'route' => 'dashboard.privacy.index',
             'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
             'perm' => null],
        ],
    ],
];

// Helper: a nav item is visible if (a) it has no perm requirement, or
// (b) the user holds the catalog permission, or (c) it has a role allowlist
// that the user matches (used by the gov section, which lives outside the
// catalog). An optional `except_roles` denylist runs first so an entry
// can be hidden from a specific role even when its permission gate
// would otherwise let it through (used by the company-profile entry,
// which would otherwise leak into the admin sidebar).
$visible = function (array $item) use ($authUser, $role): bool {
    $exceptRoles = $item['except_roles'] ?? null;
    if (!empty($exceptRoles) && in_array($role, $exceptRoles, true)) {
        return false;
    }
    $roles = $item['roles'] ?? null;
    if (!empty($roles)) {
        return in_array('*', $roles, true) || in_array($role, $roles, true);
    }
    $perm = $item['perm'] ?? null;
    if ($perm === null) {
        return true;
    }
    return (bool) $authUser?->hasPermission($perm);
};

// Format a badge count compactly: 1.2k, 99+, etc.
$fmtBadge = function (int $n): string {
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
    }
    return $n > 99 ? '99+' : (string) $n;
};
@endphp

<aside id="sidebar" class="fixed inset-y-0 start-0 z-40 w-[300px] bg-surface border-e border-th-border flex flex-col -translate-x-full lg:translate-x-0 rtl:translate-x-full lg:rtl:translate-x-0 transition-transform duration-300">

    {{-- Logo --}}
    <div class="h-[72px] flex items-center justify-center px-6 border-b border-th-border flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-12 w-auto dark:brightness-100 brightness-0" />
        </a>
    </div>

    {{-- Role badge --}}
    <div class="px-4 pt-5 flex-shrink-0">
        <div class="rounded-xl border-2 px-4 py-2.5 text-center"
             style="border-color: {{ $roleBadge['ring'] }}; background: {{ $roleBadge['bg'] }};">
            <p class="text-[12px] font-bold uppercase tracking-[0.18em]" style="color: {{ $roleBadge['text'] }};">
                {{ strtoupper(__('role.' . $role)) }}
            </p>
        </div>
    </div>

    {{-- ─────────────────────── Nav scroll area ─────────────────────── --}}
    <nav class="flex-1 overflow-y-auto px-4 py-5 space-y-6">

        {{-- Main --}}
        <div class="space-y-1">
            @foreach($nav['main'] as $item)
                @if($visible($item))
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-[12px] text-[13px] font-semibold transition-all {{ $isActive ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    <span>{{ $item['label'] }}</span>
                </a>
                @endif
            @endforeach
        </div>

        {{-- ─────────────────────── Admin section ─────────────────────── --}}
        @if($role === 'admin')
        <div>
            <h3 class="text-[10px] font-bold uppercase tracking-[0.12em] text-faint mb-2.5 px-3 flex items-center gap-2">
                <span class="w-1 h-1 rounded-full bg-[#ff4d7f]"></span>
                {{ __('admin.title') }}
            </h3>
            <div class="space-y-1">
                @php
                    // Each admin link carries its own icon so the sidebar mirrors
                    // the admin tabs and stays scannable.
                    $adminLinks = [
                        ['key' => 'admin',              'label' => __('admin.tabs.overview'),     'route' => 'admin.index',
                         'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
                        ['key' => 'admin-users',        'label' => __('admin.tabs.users'),        'route' => 'admin.users.index',
                         'icon' => 'M16 14a4 4 0 10-8 0M12 11a3 3 0 100-6 3 3 0 000 6zM4 20a8 8 0 0116 0', 'badge' => 'admin-users'],
                        ['key' => 'admin-companies',    'label' => __('admin.tabs.companies'),    'route' => 'admin.companies.index',
                         'icon' => 'M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18', 'badge' => 'admin-companies'],
                        ['key' => 'admin-verification', 'label' => __('admin.tabs.verification'), 'route' => 'admin.verification.index',
                         'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'badge' => 'admin-verification'],
                        ['key' => 'admin-oversight',    'label' => __('admin.tabs.oversight'),    'route' => 'admin.oversight.index',
                         'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
                        ['key' => 'admin-categories',   'label' => __('admin.tabs.categories'),   'route' => 'admin.categories.index',
                         'icon' => 'M3 7h18M3 12h18M3 17h18', 'badge' => 'admin-categories'],
                        ['key' => 'admin-tax-rates',    'label' => __('admin.tabs.tax_rates'),    'route' => 'admin.tax-rates.index',
                         'icon' => 'M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z', 'badge' => 'admin-tax-rates'],
                        ['key' => 'admin-tax-invoices', 'label' => __('admin.tabs.tax_invoices'), 'route' => 'admin.tax-invoices.index',
                         'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['key' => 'admin-icv-certificates', 'label' => __('admin.tabs.icv_certificates'), 'route' => 'admin.icv-certificates.index',
                         'icon' => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z'],
                        ['key' => 'admin-certificate-uploads', 'label' => __('admin.tabs.certificate_uploads'), 'route' => 'admin.certificate-uploads.index',
                         'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                        ['key' => 'admin-e-invoice', 'label' => __('admin.tabs.e_invoice'), 'route' => 'admin.e-invoice.index',
                         'icon' => 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941'],
                        ['key' => 'admin-anti-collusion', 'label' => __('admin.tabs.anti_collusion'), 'route' => 'admin.anti-collusion.index',
                         'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
                        ['key' => 'admin-settings',     'label' => __('admin.tabs.settings'),     'route' => 'admin.settings.index',
                         'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.241.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z', 'badge' => 'admin-settings'],
                        ['key' => 'admin-audit',        'label' => __('admin.tabs.audit'),        'route' => 'admin.audit.index',
                         'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'badge' => 'admin-audit'],
                    ];
                @endphp
                @foreach($adminLinks as $link)
                @php
                    $isActive = $active === $link['key'];
                    $cnt = isset($link['badge']) ? ($badges[$link['badge']] ?? 0) : 0;
                @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-[12px] text-[13px] font-medium transition-all {{ $isActive ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                    <span class="flex-1 truncate">{{ $link['label'] }}</span>
                    @if($cnt > 0)
                    <span class="flex-shrink-0 min-w-[24px] h-[20px] px-1.5 rounded-full text-[10px] font-bold flex items-center justify-center {{ $isActive ? 'bg-white/20 text-white' : 'bg-surface-2 text-muted border border-th-border' }}">
                        {{ $fmtBadge($cnt) }}
                    </span>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ─────────────────────── Standard sections ─────────────────────── --}}
        @foreach(['procurement', 'operations', 'management', 'analytics', 'settings'] as $section)
            @php
                $visibleItems = collect($nav[$section]['items'])->filter($visible);
            @endphp
            @if($visibleItems->isNotEmpty())
            <div>
                <h3 class="text-[10px] font-bold uppercase tracking-[0.12em] text-faint mb-2.5 px-3 flex items-center gap-2">
                    @php
                        $secColor = match ($section) {
                            'procurement' => '#4f7cff',
                            'operations'  => '#00d9b5',
                            'management'  => '#8B5CF6',
                            'analytics'   => '#ffb020',
                            'settings'    => '#14B8A6',
                            default       => '#4f7cff',
                        };
                    @endphp
                    <span class="w-1 h-1 rounded-full" style="background: {{ $secColor }};"></span>
                    {{ $nav[$section]['label'] }}
                </h3>
                <div class="space-y-1">
                    @foreach($visibleItems as $item)
                    @php
                        $href = $item['route']
                            ? route($item['route'], $item['route_query'] ?? [])
                            : '#';
                        $cnt = isset($item['badge']) ? ($badges[$item['badge']] ?? 0) : 0;
                        $isActive = $active === $item['key'];
                    @endphp
                    <a href="{{ $href }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-[12px] text-[13px] font-semibold transition-all {{ $isActive ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                        <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if($cnt > 0)
                        <span class="flex-shrink-0 min-w-[24px] h-[20px] px-1.5 rounded-full text-[10px] font-bold flex items-center justify-center {{ $isActive ? 'bg-white/20 text-white' : 'bg-surface-2 text-muted border border-th-border' }}">
                            {{ $fmtBadge($cnt) }}
                        </span>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
    </nav>

    {{-- User profile card --}}
    <div class="p-4 border-t border-th-border flex-shrink-0">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 p-2 rounded-xl hover:bg-surface-2 transition-colors">
            <div class="w-10 h-10 rounded-full bg-accent flex items-center justify-center text-white font-bold text-[14px] flex-shrink-0">
                {{ strtoupper(substr($authUser?->first_name ?? 'U', 0, 1) . substr($authUser?->last_name ?? '', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-semibold text-primary truncate">{{ trim(($authUser?->first_name ?? '') . ' ' . ($authUser?->last_name ?? '')) ?: 'User' }}</p>
                <p class="text-[11px] text-muted truncate">{{ __('role.' . $role) }}</p>
            </div>
        </a>
    </div>
</aside>

{{-- Mobile backdrop --}}
<div id="sidebar-backdrop" class="hidden lg:hidden fixed inset-0 z-30 bg-black/50"></div>

@push('scripts')
<script>
window.toggleSidebar = function() {
    const sb = document.getElementById('sidebar');
    const bd = document.getElementById('sidebar-backdrop');
    sb.classList.toggle('-translate-x-full');
    sb.classList.toggle('rtl:translate-x-full');
    bd.classList.toggle('hidden');
};
document.getElementById('sidebar-backdrop')?.addEventListener('click', window.toggleSidebar);
</script>
@endpush
