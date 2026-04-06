@props(['active' => 'dashboard'])

@php
// Authenticated user + role (string).
$authUser = auth()->user();
$role = $authUser?->role?->value ?? 'buyer';

// Color scheme for the role badge — each role gets a distinct accent.
$roleBadgeColors = [
    'buyer'            => ['ring' => '#3B82F6', 'text' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.08)'],
    'company_manager'  => ['ring' => '#3B82F6', 'text' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.08)'],
    'supplier'         => ['ring' => '#10B981', 'text' => '#10B981', 'bg' => 'rgba(16,185,129,0.08)'],
    'service_provider' => ['ring' => '#10B981', 'text' => '#10B981', 'bg' => 'rgba(16,185,129,0.08)'],
    'logistics'        => ['ring' => '#8B5CF6', 'text' => '#8B5CF6', 'bg' => 'rgba(139,92,246,0.08)'],
    'clearance'        => ['ring' => '#F59E0B', 'text' => '#F59E0B', 'bg' => 'rgba(245,158,11,0.08)'],
    'government'       => ['ring' => '#EF4444', 'text' => '#EF4444', 'bg' => 'rgba(239,68,68,0.08)'],
    'admin'            => ['ring' => '#EC4899', 'text' => '#EC4899', 'bg' => 'rgba(236,72,153,0.08)'],
];
$roleBadge = $roleBadgeColors[$role] ?? $roleBadgeColors['buyer'];

// Each menu item declares the permission key it requires. The item is hidden
// unless the authenticated user actually holds that permission (checked via
// User::hasPermission, which honours per-user allowlists set by the company
// manager). `null` perm = always visible (e.g. Dashboard for everyone).
// `roles` is kept as a coarse role gate for sections like Government console
// that aren't part of the per-user catalog.
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
             'perm' => 'pr.view'],
            ['key' => 'rfqs', 'label' => __('nav.rfqs'), 'route' => 'dashboard.rfqs',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => 'rfq.view'],
            ['key' => 'bids', 'label' => __('nav.bids'), 'route' => 'dashboard.bids',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => 'bid.view'],
            ['key' => 'contracts', 'label' => __('nav.contracts'), 'route' => 'dashboard.contracts',
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
             'perm' => 'contract.view'],
        ],
    ],
    'operations' => [
        'label' => __('nav.operations'),
        'items' => [
            ['key' => 'shipments', 'label' => __('nav.shipment_tracking'), 'route' => 'dashboard.shipments',
             'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
             'perm' => 'shipment.view'],
            ['key' => 'payments', 'label' => __('nav.payment_management'), 'route' => 'dashboard.payments',
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
             'perm' => 'payment.view'],
            ['key' => 'disputes', 'label' => __('nav.disputes'), 'route' => 'dashboard.disputes',
             'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
             'perm' => 'dispute.view'],
        ],
    ],
    'management' => [
        'label' => __('nav.management'),
        'items' => [
            ['key' => 'company-users', 'label' => __('company.users.title'), 'route' => 'company.users',
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
             'perm' => 'team.view'],
            ['key' => 'gov', 'label' => __('gov.title'), 'route' => 'gov.index',
             'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z',
             'perm' => null, 'roles' => ['government', 'admin']],
        ],
    ],
    'analytics' => [
        'label' => __('nav.insights'),
        'items' => [
            ['key' => 'performance', 'label' => __('nav.performance'), 'route' => 'performance.index',
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
        ],
    ],
];

// Helper: a nav item is visible if (a) it has no perm requirement, or
// (b) the user holds the catalog permission, or (c) it has a role allowlist
// that the user matches (used by the gov section, which lives outside the
// catalog).
$visible = function (array $item) use ($authUser, $role): bool {
    // A coarse role allowlist (used by the gov console). Optional — most
    // items don't set it, so guard the array access with `??`.
    $roles = $item['roles'] ?? null;
    if (!empty($roles)) {
        return in_array('*', $roles, true) || in_array($role, $roles, true);
    }

    // Otherwise fall back to the catalog permission check. `perm` is also
    // optional; if it's null/missing the item is unconditionally visible.
    $perm = $item['perm'] ?? null;
    if ($perm === null) {
        return true;
    }
    return (bool) $authUser?->hasPermission($perm);
};
@endphp

<aside id="sidebar" class="fixed inset-y-0 start-0 z-40 w-[280px] bg-surface border-e border-th-border flex flex-col -translate-x-full lg:translate-x-0 rtl:translate-x-full lg:rtl:translate-x-0 transition-transform duration-300">

    {{-- Logo --}}
    <div class="h-[72px] flex items-center justify-center px-6 border-b border-th-border">
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-12 w-auto dark:brightness-100 brightness-0" />
        </a>
    </div>

    {{-- Role badge --}}
    <div class="px-4 pt-5">
        <div class="rounded-xl border-2 px-4 py-2.5 text-center"
             style="border-color: {{ $roleBadge['ring'] }}; background: {{ $roleBadge['bg'] }};">
            <p class="text-[12px] font-bold uppercase tracking-[0.18em]" style="color: {{ $roleBadge['text'] }};">
                {{ strtoupper(__('role.' . $role)) }}
            </p>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-6">

        {{-- Main --}}
        <div class="space-y-1">
            @foreach($nav['main'] as $item)
                @if($visible($item))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-[14px] font-medium transition-colors {{ $active === $item['key'] ? 'bg-accent text-white shadow-[0_4px_14px_rgba(37,99,235,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    <span>{{ $item['label'] }}</span>
                </a>
                @endif
            @endforeach
        </div>

        @if($role === 'admin')
        <div>
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-faint mb-3 px-3">{{ __('admin.title') }}</h3>
            <div class="space-y-1">
                @php
                    $adminLinks = [
                        ['key' => 'admin',            'label' => __('admin.tabs.overview'),   'route' => 'admin.index'],
                        ['key' => 'admin-users',      'label' => __('admin.tabs.users'),      'route' => 'admin.users.index'],
                        ['key' => 'admin-companies',  'label' => __('admin.tabs.companies'),  'route' => 'admin.companies.index'],
                        ['key' => 'admin-categories', 'label' => __('admin.tabs.categories'), 'route' => 'admin.categories.index'],
                        ['key' => 'admin-settings',   'label' => __('admin.tabs.settings'),   'route' => 'admin.settings.index'],
                        ['key' => 'admin-audit',      'label' => __('admin.tabs.audit'),      'route' => 'admin.audit.index'],
                    ];
                @endphp
                @foreach($adminLinks as $link)
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors {{ $active === $link['key'] ? 'bg-accent/15 text-accent' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60"></span>
                    {{ $link['label'] }}
                </a>
                @endforeach
            </div>
        </div>
        @endif

        @foreach(['procurement', 'operations', 'management', 'analytics', 'settings'] as $section)
            @php
                $visibleItems = collect($nav[$section]['items'])->filter($visible);
            @endphp
            @if($visibleItems->isNotEmpty())
            <div>
                <h3 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-faint mb-3 px-3">{{ $nav[$section]['label'] }}</h3>
                <div class="space-y-1">
                    @foreach($visibleItems as $item)
                    <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                       class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-[14px] font-medium transition-colors {{ $active === $item['key'] ? 'bg-accent text-white shadow-[0_4px_14px_rgba(37,99,235,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                            {{ $item['label'] }}
                        </span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
    </nav>

    {{-- User profile card --}}
    <div class="p-4 border-t border-th-border">
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
