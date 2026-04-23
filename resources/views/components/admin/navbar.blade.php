@props(['active' => 'overview'])

@php
/**
 * Admin console navbar.
 *
 * Drop-in replacement for the legacy `dashboard.admin._tabs` include.
 * Renders a polished console header (title + role pill + back link)
 * followed by a horizontal pill tab bar with live badge counts pulled
 * from SidebarBadgeService.
 *
 * Usage: <x-admin.navbar active="users" />
 *
 * The active tab key matches the existing `_tabs` keys so callers can
 * be migrated 1:1 without changing their `active` value.
 */

// Pull badge counts from the same cached service the sidebar uses, so the
// admin navbar stays in sync with the sidebar dots without re-querying.
$adminBadges = app(\App\Services\SidebarBadgeService::class)->for(auth()->user());

$tabs = [
    [
        'key'   => 'overview',
        'label' => __('admin.tabs.overview'),
        'route' => 'admin.index',
        'icon'  => '<path d="M3 12l9-9 9 9M5 10v10h14V10"/>',
    ],
    [
        'key'   => 'users',
        'label' => __('admin.tabs.users'),
        'route' => 'admin.users.index',
        'badge' => 'admin-users',
        'icon'  => '<path d="M16 14a4 4 0 10-8 0M12 11a3 3 0 100-6 3 3 0 000 6zM4 20a8 8 0 0116 0"/>',
    ],
    [
        'key'   => 'companies',
        'label' => __('admin.tabs.companies'),
        'route' => 'admin.companies.index',
        'badge' => 'admin-companies',
        'icon'  => '<path d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>',
    ],
    [
        'key'   => 'verification',
        'label' => __('admin.tabs.verification'),
        'route' => 'admin.verification.index',
        'badge' => 'admin-verification',
        'icon'  => '<path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ],
    [
        'key'   => 'oversight',
        'label' => __('admin.tabs.oversight'),
        'route' => 'admin.oversight.index',
        'icon'  => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    ],
    [
        'key'   => 'design-partners',
        'label' => __('admin.tabs.design_partners'),
        'route' => 'admin.design-partners.index',
        'icon'  => '<path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>',
    ],
    [
        'key'   => 'categories',
        'label' => __('admin.tabs.categories'),
        'route' => 'admin.categories.index',
        'badge' => 'admin-categories',
        'icon'  => '<path d="M3 7h18M3 12h18M3 17h18"/>',
    ],
    [
        'key'   => 'category-requests',
        'label' => __('admin.tabs.category_requests'),
        'route' => 'admin.category-requests.index',
        'badge' => 'admin-category-requests',
        'icon'  => '<path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
    ],
    [
        'key'   => 'tax-rates',
        'label' => __('admin.tabs.tax_rates'),
        'route' => 'admin.tax-rates.index',
        'badge' => 'admin-tax-rates',
        'icon'  => '<path d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
    ],
    [
        'key'   => 'settings',
        'label' => __('admin.tabs.settings'),
        'route' => 'admin.settings.index',
        'badge' => 'admin-settings',
        'icon'  => '<path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ],
    [
        'key'   => 'audit',
        'label' => __('admin.tabs.audit'),
        'route' => 'admin.audit.index',
        'badge' => 'admin-audit',
        'icon'  => '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
    ],
    [
        'key'   => 'reports',
        'label' => __('admin.tabs.reports'),
        'route' => 'admin.reports.index',
        'icon'  => '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
    ],
    [
        'key'   => 'health',
        'label' => __('admin.tabs.health'),
        'route' => 'admin.system-health.index',
        'icon'  => '<path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
    ],
    [
        'key'   => 'disputes',
        'label' => __('admin.tabs.disputes'),
        'route' => 'admin.disputes.index',
        'icon'  => '<path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>',
    ],
    [
        'key'   => 'blacklist',
        'label' => __('admin.tabs.blacklist'),
        'route' => 'admin.blacklist.index',
        'icon'  => '<path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
    ],
    [
        'key'   => 'fees',
        'label' => __('admin.tabs.fees'),
        'route' => 'admin.fees.index',
        'icon'  => '<path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4"/>',
    ],
    [
        'key'   => 'exchange-rates',
        'label' => __('admin.tabs.exchange_rates'),
        'route' => 'admin.exchange-rates.index',
        'icon'  => '<path d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/>',
    ],
    [
        'key'   => 'webhooks',
        'label' => __('admin.tabs.webhooks'),
        'route' => 'admin.webhooks.index',
        'icon'  => '<path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-6.818l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/>',
    ],
];

// Compact badge formatter shared with the sidebar (1.2k / 99+).
$fmtBadge = function (int $n): string {
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
    }
    return $n > 99 ? '99+' : (string) $n;
};
@endphp

{{-- ─────────────────────── Console header card ─────────────────────── --}}
<div class="mb-5 bg-surface border border-th-border rounded-[16px] px-5 py-4 sm:px-6 sm:py-5 flex flex-col sm:flex-row sm:items-center gap-4">

    {{-- Title + subtitle --}}
    <div class="flex items-center gap-4 flex-1 min-w-0">
        <div class="w-[44px] h-[44px] rounded-[12px] flex items-center justify-center flex-shrink-0"
             style="background: linear-gradient(180deg, rgba(255,77,127,0.18) 0%, rgba(255,77,127,0.06) 100%); border: 1px solid rgba(255,77,127,0.35);">
            <svg class="w-[20px] h-[20px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <h1 class="font-display text-[18px] sm:text-[20px] font-bold text-primary tracking-[-0.018em] leading-none truncate">
                    {{ __('admin.title') }}
                </h1>
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] rounded-full bg-[rgba(255,77,127,0.1)] border border-[rgba(255,77,127,0.25)] text-[#ff4d7f]">
                    <span class="w-1 h-1 rounded-full bg-[#ff4d7f] animate-pulse"></span>
                    {{ __('admin.navbar.live') }}
                </span>
            </div>
            <p class="text-[12px] text-muted leading-snug truncate">{{ __('admin.navbar.console_subtitle') }}</p>
        </div>
    </div>

    {{-- Quick actions: search shortcut + back to dashboard --}}
    <div class="flex items-center gap-2 flex-shrink-0">
        <span class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-full bg-[rgba(255,77,127,0.1)] border border-[rgba(255,77,127,0.25)] text-[#ff4d7f]">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            {{ __('admin.navbar.role_pill') }}
        </span>
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center gap-2 px-3.5 h-9 rounded-[10px] bg-surface-2 hover:bg-elevated border border-th-border text-[12px] font-semibold text-body hover:text-primary transition-colors">
            <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m12 19-7-7 7-7M19 12H5"/></svg>
            <span class="hidden sm:inline">{{ __('admin.navbar.back_to_dashboard') }}</span>
        </a>
    </div>
</div>

{{-- ─────────────────────── Pill tab bar ─────────────────────── --}}
<div class="mb-6 bg-surface border border-th-border rounded-[16px] p-[6px]">
    <div class="flex items-center gap-1 overflow-x-auto scrollbar-none">
        @foreach($tabs as $t)
            @php
                $isActive = $active === $t['key'];
                $cnt = isset($t['badge']) ? ($adminBadges[$t['badge']] ?? 0) : 0;
            @endphp
            <a href="{{ route($t['route']) }}"
               class="group relative inline-flex items-center gap-2 px-4 h-11 rounded-[12px] text-[13px] font-semibold whitespace-nowrap transition-all flex-shrink-0
                      {{ $isActive
                            ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.35)]'
                            : 'text-muted hover:text-primary hover:bg-surface-2' }}">
                <svg class="w-[16px] h-[16px] transition-transform group-hover:scale-110"
                     fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                     stroke-linecap="round" stroke-linejoin="round">
                    {!! $t['icon'] !!}
                </svg>
                <span>{{ $t['label'] }}</span>
                @if($cnt > 0)
                    <span class="ms-0.5 inline-flex items-center justify-center min-w-[20px] h-[18px] px-1.5 rounded-full text-[10px] font-bold leading-none
                                 {{ $isActive
                                        ? 'bg-white/25 text-white'
                                        : 'bg-surface-2 text-muted border border-th-border group-hover:border-accent/30' }}">
                        {{ $fmtBadge($cnt) }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</div>

{{-- ─────────────────────── Flash messages ─────────────────────── --}}
@if(session('status'))
<div class="mb-5 rounded-[12px] border border-[#00d9b5]/30 bg-[#00d9b5]/10 px-4 py-3 text-[13px] text-[#00d9b5] flex items-center gap-3">
    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span class="font-medium">{{ session('status') }}</span>
</div>
@endif

@if(isset($errors) && $errors->any())
<div class="mb-5 rounded-[12px] border border-[#ff4d7f]/30 bg-[#ff4d7f]/10 px-4 py-3 text-[13px] text-[#ff4d7f]">
    <div class="flex items-start gap-3">
        <svg class="w-[18px] h-[18px] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        <ul class="space-y-1 font-medium">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif
