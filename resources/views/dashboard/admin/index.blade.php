@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.title'))

@php
/**
 * Stat-card icons — single-path Heroicons inlined so each tile reads as
 * a labelled control rather than a colored slab. Keys mirror the stat
 * keys from AdminController so every card knows which symbol it owns.
 */
$icons = [
    'users'              => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
    'active_users'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'companies'          => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>',
    'active_companies'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'purchase_requests'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
    'rfqs'               => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
    'open_rfqs'          => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3M16 12l-4 4-4-4M12 16V4"/>',
    'bids'               => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.84L3 20l1.34-3.36A7.97 7.97 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    'contracts'          => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2c0 5.523-4.477 10-10 10S1 17.523 1 12 5.477 2 11 2s10 4.477 10 10z"/>',
    'active_contracts'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M9 15l2 2 4-4"/>',
    'contract_value'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4"/>',
    'payments_completed' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
    'escrow_active'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    'escrow_balance'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75M3 6v9.75c0 .621.504 1.125 1.125 1.125H20.25M3 6h17.25M21 6v9.75c0 .621-.504 1.125-1.125 1.125H3.75M21 6V4.5M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    'in_transit'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1"/>',
    'products'           => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
];
@endphp

@section('content')

<x-dashboard.page-header :title="__('admin.title')" :subtitle="trim((auth()->user()->first_name ?? '') . ' · ' . __('admin.system_administrator'))" />

<x-admin.navbar active="overview" />

{{-- ─────────────────────────── Hero strip — quick health glance ─────────────────────── --}}
@php
    $totalAttention   = (int) collect($attention)->sum('count');
    $healthState      = $totalAttention === 0 ? 'healthy' : ($totalAttention < 5 ? 'attention' : 'critical');
    $healthColors     = [
        'healthy'   => ['dot' => '#00d9b5', 'label' => __('admin.health.healthy'),   'desc' => __('admin.health.healthy_desc')],
        'attention' => ['dot' => '#ffb020', 'label' => __('admin.health.attention'), 'desc' => __('admin.health.attention_desc')],
        'critical'  => ['dot' => '#ff4d7f', 'label' => __('admin.health.critical'),  'desc' => __('admin.health.critical_desc')],
    ];
    $hs = $healthColors[$healthState];
@endphp

<div class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-[25px] mb-6 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none opacity-[0.06]" style="background: radial-gradient(circle at 90% 10%, {{ $hs['dot'] }} 0%, transparent 50%);"></div>
    <div class="relative flex items-start sm:items-center justify-between gap-4 sm:gap-6 flex-wrap">
        <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-[16px] bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-faint mb-1">{{ __('admin.health.title') }}</p>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $hs['dot'] }}; box-shadow: 0 0 0 3px {{ $hs['dot'] }}33;"></span>
                    <p class="text-[16px] sm:text-[18px] font-bold text-primary leading-tight">{{ $hs['label'] }}</p>
                </div>
                <p class="text-[12px] text-muted mt-0.5">{{ $hs['desc'] }}</p>
            </div>
        </div>
        <div class="flex items-center gap-5 sm:gap-6 flex-wrap pt-1 sm:pt-0">
            <div class="text-end">
                <p class="text-[10px] sm:text-[11px] uppercase tracking-wider text-faint">{{ __('admin.health.queue_items') }}</p>
                <p class="text-[20px] sm:text-[24px] font-bold text-primary leading-none mt-1">{{ number_format($totalAttention) }}</p>
            </div>
            <div class="text-end">
                <p class="text-[10px] sm:text-[11px] uppercase tracking-wider text-faint">{{ __('admin.health.now') }}</p>
                <p class="text-[13px] sm:text-[14px] font-semibold text-primary mt-1" id="adminHeroClock">{{ now()->format('M j, H:i') }}</p>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var el = document.getElementById('adminHeroClock');
        if (!el) return;
        function tick() {
            var d = new Date();
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var hh = String(d.getHours()).padStart(2, '0');
            var mm = String(d.getMinutes()).padStart(2, '0');
            el.textContent = months[d.getMonth()] + ' ' + d.getDate() + ', ' + hh + ':' + mm;
        }
        tick();
        setInterval(tick, 30000);
    })();
</script>

{{-- ───────────────────────── Section 1 — Identity & companies ───────────────────────── --}}
<div class="flex items-center gap-3 mb-3">
    <span class="w-1 h-4 rounded-full bg-accent"></span>
    <h3 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('admin.section.identity') }}</h3>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <x-dashboard.stat-card
        :value="number_format($stats['users'])"
        :label="__('admin.users')"
        color="blue"
        :icon="$icons['users']"
        :href="route('admin.users.index')" />
    <x-dashboard.stat-card
        :value="number_format($stats['active_users'])"
        :label="__('admin.users.active')"
        color="green"
        :icon="$icons['active_users']"
        :href="route('admin.users.index', ['status' => 'active'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['companies'])"
        :label="__('admin.companies')"
        color="purple"
        :icon="$icons['companies']"
        :href="route('admin.companies.index')" />
    <x-dashboard.stat-card
        :value="number_format($stats['active_companies'])"
        :label="__('admin.companies.active')"
        color="green"
        :icon="$icons['active_companies']"
        :href="route('admin.companies.index', ['status' => 'active'])" />
</div>

{{-- ───────────────────────── Section 2 — Procurement workflow ─────────────────────── --}}
<div class="flex items-center gap-3 mb-3">
    <span class="w-1 h-4 rounded-full bg-[#8B5CF6]"></span>
    <h3 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('admin.section.procurement') }}</h3>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <x-dashboard.stat-card
        :value="number_format($stats['purchase_requests'])"
        :label="__('admin.oversight.purchase_requests')"
        color="blue"
        :icon="$icons['purchase_requests']"
        :href="route('admin.oversight.index', ['scope' => 'purchase_requests'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['rfqs'])"
        :label="__('admin.metric.rfqs')"
        color="purple"
        :icon="$icons['rfqs']"
        :href="route('admin.oversight.index', ['scope' => 'rfqs'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['open_rfqs'])"
        :label="__('admin.metric.open_rfqs')"
        color="orange"
        :icon="$icons['open_rfqs']"
        :href="route('admin.oversight.index', ['scope' => 'rfqs'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['bids'])"
        :label="__('admin.metric.bids')"
        color="teal"
        :icon="$icons['bids']"
        :href="route('admin.oversight.index', ['scope' => 'bids'])" />
</div>

{{-- ───────────────────────── Section 3 — Contracts & money ───────────────────────── --}}
<div class="flex items-center gap-3 mb-3">
    <span class="w-1 h-4 rounded-full bg-[#00d9b5]"></span>
    <h3 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('admin.section.financial') }}</h3>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <x-dashboard.stat-card
        :value="number_format($stats['contracts'])"
        :label="__('admin.metric.contracts')"
        color="purple"
        :icon="$icons['contracts']"
        :href="route('admin.oversight.index', ['scope' => 'contracts'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['active_contracts'])"
        :label="__('admin.metric.active_contracts')"
        color="green"
        :icon="$icons['active_contracts']"
        :href="route('admin.oversight.index', ['scope' => 'contracts'])" />
    <x-dashboard.stat-card
        :value="'$' . number_format($stats['contract_value'], 0)"
        :label="__('admin.metric.contract_value')"
        color="teal"
        :icon="$icons['contract_value']" />
    <x-dashboard.stat-card
        :value="'$' . number_format($stats['payments_completed'], 0)"
        :label="__('admin.metric.payments_completed')"
        color="green"
        :icon="$icons['payments_completed']"
        :href="route('admin.oversight.index', ['scope' => 'payments'])" />
</div>

{{-- ───────────────────────── Section 4 — Trade finance, logistics & catalog ──────── --}}
<div class="flex items-center gap-3 mb-3">
    <span class="w-1 h-4 rounded-full bg-[#ffb020]"></span>
    <h3 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('admin.section.operations') }}</h3>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <x-dashboard.stat-card
        :value="number_format($stats['escrow_active'])"
        :label="__('admin.metric.escrow_active')"
        color="teal"
        :icon="$icons['escrow_active']" />
    <x-dashboard.stat-card
        :value="'$' . number_format($stats['escrow_balance'], 0)"
        :label="__('admin.metric.escrow_balance')"
        color="green"
        :icon="$icons['escrow_balance']" />
    <x-dashboard.stat-card
        :value="number_format($stats['in_transit_shipments'])"
        :label="__('admin.metric.in_transit')"
        color="orange"
        :icon="$icons['in_transit']"
        :href="route('admin.oversight.index', ['scope' => 'shipments'])" />
    <x-dashboard.stat-card
        :value="number_format($stats['products'])"
        :label="__('admin.metric.products')"
        color="purple"
        :icon="$icons['products']" />
</div>

{{-- ───────────────────────── Needs attention banner ──────────────────────────────── --}}
@php
    $colorPills = [
        'orange' => ['border' => 'border-[#ffb020]/30', 'bg' => 'bg-[#ffb020]/[0.06]', 'hoverBg' => 'hover:bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]'],
        'red'    => ['border' => 'border-[#ff4d7f]/30', 'bg' => 'bg-[#ff4d7f]/[0.06]', 'hoverBg' => 'hover:bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'dot' => 'bg-[#ff4d7f]'],
        'blue'   => ['border' => 'border-[#4f7cff]/30', 'bg' => 'bg-[#4f7cff]/[0.06]', 'hoverBg' => 'hover:bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'dot' => 'bg-[#4f7cff]'],
        'purple' => ['border' => 'border-[#8B5CF6]/30', 'bg' => 'bg-[#8B5CF6]/[0.06]', 'hoverBg' => 'hover:bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'dot' => 'bg-[#8B5CF6]'],
        'teal'   => ['border' => 'border-[#00d9b5]/30', 'bg' => 'bg-[#00d9b5]/[0.06]', 'hoverBg' => 'hover:bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    ];
@endphp
<div class="bg-surface border border-th-border rounded-[16px] p-[25px] mb-8">
    <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[12px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                <svg class="w-[18px] h-[18px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
            <div>
                <h3 class="text-[16px] font-bold text-primary leading-tight">{{ __('admin.attention.title') }}</h3>
                <p class="text-[12px] text-muted">{{ __('admin.attention.subtitle') }}</p>
            </div>
        </div>
        @if($totalAttention > 0)
        <span class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/30 text-[12px] font-bold text-[#ffb020]">
            {{ number_format($totalAttention) }} {{ __('admin.health.queue_items') }}
        </span>
        @endif
    </div>

    @if($totalAttention > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($attention as $item)
            @if($item['count'] > 0)
            @php $cls = $colorPills[$item['color']] ?? $colorPills['blue']; @endphp
            <a href="{{ $item['route'] }}"
               class="group flex items-center justify-between gap-3 rounded-[12px] border {{ $cls['border'] }} {{ $cls['bg'] }} {{ $cls['hoverBg'] }} px-4 py-3 transition-all">
                <div class="min-w-0 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-[10px] {{ $cls['bg'] }} border {{ $cls['border'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-[15px] h-[15px] {{ $cls['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    </span>
                    <p class="text-[11px] uppercase tracking-wider {{ $cls['text'] }} truncate font-semibold">{{ $item['label'] }}</p>
                </div>
                <span class="text-[18px] font-bold {{ $cls['text'] }} flex-shrink-0">{{ $item['count'] }}</span>
            </a>
            @endif
        @endforeach
    </div>
    @else
    <div class="rounded-[12px] border border-[#00d9b5]/30 bg-[#00d9b5]/[0.06] px-6 py-8 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-[#00d9b5]/15 flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-[14px] font-bold text-[#00d9b5]">{{ __('admin.attention.all_clear') }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.attention.all_clear_subtitle') }}</p>
    </div>
    @endif
</div>

{{-- ───────────────────────── Recent users + companies (two-up) ───────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('admin.recent_users') }}</h3>
            </div>
            <a href="{{ route('admin.users.index') }}" class="text-[12px] font-semibold text-accent hover:underline inline-flex items-center gap-1">
                {{ __('common.view_all') }}
                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="space-y-2">
            @forelse($recentUsers as $u)
            <div class="flex items-center gap-3 rounded-[12px] hover:bg-surface-2 transition-colors px-2 py-2">
                <div class="w-10 h-10 rounded-full bg-accent/10 border border-accent/20 text-accent font-bold flex items-center justify-center text-[12px] flex-shrink-0">
                    {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary truncate">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                    <p class="text-[11px] text-muted truncate">{{ $u->email }} · {{ $u->company?->name ?? '—' }}</p>
                </div>
                <span class="text-[10px] font-semibold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5 flex-shrink-0">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span>
            </div>
            @empty
            <p class="text-[13px] text-muted py-3 text-center">{{ __('admin.no_users_yet') }}</p>
            @endforelse
        </div>
    </div>

    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('gov.recent_companies') }}</h3>
            </div>
            <a href="{{ route('admin.companies.index') }}" class="text-[12px] font-semibold text-accent hover:underline inline-flex items-center gap-1">
                {{ __('common.view_all') }}
                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="space-y-2">
            @forelse($recentCompanies as $c)
            <div class="flex items-center gap-3 rounded-[12px] hover:bg-surface-2 transition-colors px-2 py-2">
                <div class="w-10 h-10 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 text-[#8B5CF6] font-bold flex items-center justify-center text-[12px] flex-shrink-0">
                    {{ strtoupper(substr($c->name ?? 'C', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary truncate">{{ $c->name }}</p>
                    <p class="text-[11px] text-muted truncate">{{ $c->city ?? '—' }} · {{ $c->country ?? '—' }}</p>
                </div>
                <x-dashboard.status-badge :status="$c->status?->value ?? 'pending'" />
            </div>
            @empty
            <p class="text-[13px] text-muted py-3 text-center">{{ __('admin.no_companies_yet') }}</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ───────────────────────── Quick links ─────────────────────────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] p-[25px] mb-8">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-9 h-9 rounded-[10px] bg-accent/10 border border-accent/20 flex items-center justify-center">
            <svg class="w-[16px] h-[16px] text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <h3 class="text-[15px] font-bold text-primary">{{ __('admin.quick_links.title') }}</h3>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @foreach($quickLinks as $link)
        @php
            $linkColors = [
                'blue'   => ['border' => 'border-[#4f7cff]/30 hover:border-[#4f7cff]/60', 'bg' => 'hover:bg-[#4f7cff]/[0.06]', 'text' => 'text-[#4f7cff]'],
                'purple' => ['border' => 'border-[#8B5CF6]/30 hover:border-[#8B5CF6]/60', 'bg' => 'hover:bg-[#8B5CF6]/[0.06]', 'text' => 'text-[#8B5CF6]'],
                'green'  => ['border' => 'border-[#00d9b5]/30 hover:border-[#00d9b5]/60', 'bg' => 'hover:bg-[#00d9b5]/[0.06]', 'text' => 'text-[#00d9b5]'],
                'teal'   => ['border' => 'border-[#14B8A6]/30 hover:border-[#14B8A6]/60', 'bg' => 'hover:bg-[#14B8A6]/[0.06]', 'text' => 'text-[#14B8A6]'],
                'orange' => ['border' => 'border-[#ffb020]/30 hover:border-[#ffb020]/60', 'bg' => 'hover:bg-[#ffb020]/[0.06]', 'text' => 'text-[#ffb020]'],
                'red'    => ['border' => 'border-[#ff4d7f]/30 hover:border-[#ff4d7f]/60', 'bg' => 'hover:bg-[#ff4d7f]/[0.06]', 'text' => 'text-[#ff4d7f]'],
                'slate'  => ['border' => 'border-th-border hover:border-th-border', 'bg' => 'hover:bg-surface-2', 'text' => 'text-muted'],
            ];
            $cls = $linkColors[$link['color']] ?? $linkColors['blue'];
        @endphp
        <a href="{{ $link['route'] }}"
           class="group rounded-[12px] border {{ $cls['border'] }} {{ $cls['bg'] }} px-4 py-3 transition-all flex items-center gap-3">
            <span class="w-9 h-9 rounded-[10px] bg-surface-2 border border-th-border group-hover:border-current {{ $cls['text'] }} flex items-center justify-center flex-shrink-0 transition-colors">
                <svg class="w-[16px] h-[16px] {{ $cls['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] ?? 'M13 10V3L4 14h7v7l9-11h-7z' }}"/></svg>
            </span>
            <span class="text-[13px] font-semibold text-primary group-hover:text-primary truncate">{{ __('admin.quick_links.' . $link['key']) }}</span>
            <svg class="w-3.5 h-3.5 ms-auto text-faint group-hover:text-current {{ $cls['text'] }} rtl:rotate-180 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endforeach
    </div>
</div>

{{-- ───────────────────────── Audit feed ──────────────────────────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center">
                <svg class="w-[16px] h-[16px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('admin.audit') }}</h3>
        </div>
        <a href="{{ route('admin.audit.index') }}" class="text-[12px] font-semibold text-accent hover:underline inline-flex items-center gap-1">
            {{ __('common.view_all') }}
            <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
    @php
    // Map every audit action to a colored dot + tiny SVG so the feed reads
    // like a system journal rather than a flat key/value list.
    $auditMap = [
        'create'  => ['color' => 'green',  'icon' => 'M12 4v16m8-8H4'],
        'created' => ['color' => 'green',  'icon' => 'M12 4v16m8-8H4'],
        'update'  => ['color' => 'blue',   'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        'updated' => ['color' => 'blue',   'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        'delete'  => ['color' => 'red',    'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
        'deleted' => ['color' => 'red',    'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
        'login'   => ['color' => 'purple', 'icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1'],
        'logout'  => ['color' => 'orange', 'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
        'approve' => ['color' => 'green',  'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'reject'  => ['color' => 'red',    'icon' => 'M6 18L18 6M6 6l12 12'],
    ];
    $auditColorClass = [
        'green'  => ['text' => 'text-[#00d9b5]', 'bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20'],
        'blue'   => ['text' => 'text-[#4f7cff]', 'bg' => 'bg-[#4f7cff]/10', 'border' => 'border-[#4f7cff]/20'],
        'red'    => ['text' => 'text-[#ff4d7f]', 'bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/20'],
        'purple' => ['text' => 'text-[#8B5CF6]', 'bg' => 'bg-[#8B5CF6]/10', 'border' => 'border-[#8B5CF6]/20'],
        'orange' => ['text' => 'text-[#ffb020]', 'bg' => 'bg-[#ffb020]/10', 'border' => 'border-[#ffb020]/20'],
    ];
    @endphp
    <div class="divide-y divide-th-border">
        @forelse($recentAuditLogs as $log)
        @php
            $actionKey = strtolower((string) ($log->action?->value ?? $log->action ?? ''));
            $matchKey  = collect(array_keys($auditMap))->first(fn ($k) => str_contains($actionKey, $k)) ?? 'update';
            $am        = $auditMap[$matchKey];
            $ac        = $auditColorClass[$am['color']];
        @endphp
        <div class="py-2.5 flex items-center gap-3 text-[12px]">
            <span class="w-7 h-7 rounded-[8px] {{ $ac['bg'] }} border {{ $ac['border'] }} flex items-center justify-center flex-shrink-0">
                <svg class="w-[13px] h-[13px] {{ $ac['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $am['icon'] }}"/></svg>
            </span>
            <span class="text-muted w-24 flex-shrink-0">{{ $log->created_at?->diffForHumans() }}</span>
            <span class="font-mono {{ $ac['text'] }} text-[11px] {{ $ac['bg'] }} border {{ $ac['border'] }} rounded-md px-2 py-0.5 flex-shrink-0">{{ $log->action?->value ?? $log->action }}</span>
            <span class="text-body truncate">{{ $log->resource_type }}#{{ $log->resource_id }}</span>
            <span class="ms-auto text-muted truncate">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }}</span>
        </div>
        @empty
        <div class="py-8 text-center">
            <div class="mx-auto w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center mb-2">
                <svg class="w-5 h-5 text-faint" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-[13px] text-muted">{{ __('admin.no_audit_entries') }}</p>
        </div>
        @endforelse
    </div>
</div>

@endsection
