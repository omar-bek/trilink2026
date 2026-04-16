@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.title'))

@section('content')

<x-dashboard.page-header :title="__('gov.title')" :subtitle="__('gov.intelligence')" />

{{-- Stats — responsive: 1 col on mobile, 2 on small, 3 on md, 5 on xl --}}
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
    <x-dashboard.stat-card
        :value="$stats['escalated']"
        :label="__('gov.escalated_disputes')"
        color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>' />
    <x-dashboard.stat-card
        :value="$stats['resolved']"
        :label="__('disputes.resolved')"
        color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card
        :value="$stats['companies']"
        :label="__('admin.companies')"
        color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>' />
    <x-dashboard.stat-card
        :value="'AED ' . number_format($stats['gmv'])"
        :label="__('gov.active_gmv')"
        color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22M21 8.689V5.25H17.561"/>' />
    <x-dashboard.stat-card
        :value="'AED ' . number_format($stats['vat_collected'])"
        :label="__('gov.vat_collected')"
        color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>' />
</div>

{{-- Escalated queue --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-[25px]">
    <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[12px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/></svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-[15px] sm:text-[16px] font-bold text-primary leading-tight">{{ __('gov.escalated_disputes') }}</h3>
                <p class="text-[12px] text-muted mt-0.5">{{ __('gov.escalated_subtitle') ?? __('gov.intelligence') }}</p>
            </div>
        </div>
        @if(!empty($escalated) && count($escalated) > 0)
        <a href="{{ route('dashboard.disputes.index', ['status' => 'escalated']) }}" class="inline-flex items-center gap-1 text-[12px] font-semibold text-accent hover:text-accent-h transition-colors">
            {{ __('common.view_all') }}
            <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>
    <div class="space-y-4">
        @forelse($escalated as $d)
        <div class="bg-page border border-th-border rounded-[12px] p-5">
            <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">DIS-{{ $d->id }}</span>
                    <x-dashboard.status-badge :status="$d->status?->value ?? 'open'" />
                    <span class="inline-flex items-center gap-1.5 text-[10px] text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20 rounded-full px-2 py-0.5 font-semibold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        {{ __('disputes.escalated') }}
                    </span>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-muted">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ $d->created_at?->diffForHumans() }}
                </span>
            </div>
            <h4 class="text-[15px] font-bold text-primary mb-1">{{ $d->title }}</h4>
            <p class="text-[12px] text-muted mb-3">{{ \Illuminate\Support\Str::limit($d->description, 200) }}</p>
            <div class="flex items-center gap-4 text-[11px] text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9"/></svg>
                    <strong class="text-body">{{ $d->company?->name ?? '—' }}</strong>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <strong class="text-body">{{ $d->againstCompany?->name ?? '—' }}</strong>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6"/></svg>
                    <strong class="text-body">{{ $d->contract?->contract_number ?? '—' }}</strong>
                </span>
                <a href="{{ route('dashboard.disputes.show', ['id' => $d->id]) }}" class="ms-auto inline-flex items-center gap-1 text-accent font-semibold">
                    {{ __('common.review') }}
                    <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        @empty
        <div class="text-center py-12">
            <div class="mx-auto w-14 h-14 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-[13px] text-muted">{{ __('gov.no_escalated') }}</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Quick links to reports --}}
<div class="mt-8">
    <div class="flex items-center gap-3 mb-4">
        <span class="w-1 h-4 rounded-full bg-accent"></span>
        <h3 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('gov.intelligence') }}</h3>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @php
        $govLinks = [
            ['route' => 'gov.contracts',       'label' => __('gov.contracts_title'),   'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z', 'color' => '#4f7cff'],
            ['route' => 'gov.payments',        'label' => __('gov.payments_title'),    'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z', 'color' => '#00d9b5'],
            ['route' => 'gov.competition',     'label' => __('gov.competition_title'), 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z', 'color' => '#8B5CF6'],
            ['route' => 'gov.disputes',        'label' => __('gov.disputes_title'),    'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z', 'color' => '#ff4d7f'],
            ['route' => 'gov.icv-report',      'label' => __('gov.icv_title'),         'icon' => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12', 'color' => '#ffb020'],
            ['route' => 'gov.esg-report',      'label' => __('gov.esg_title'),         'icon' => 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z', 'color' => '#00d9b5'],
            ['route' => 'gov.sanctions-report', 'label' => __('gov.sanctions_title'),  'icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z', 'color' => '#ef4444'],
            ['route' => 'gov.sme-report',      'label' => __('gov.sme_title'),         'icon' => 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349', 'color' => '#4f7cff'],
            ['route' => 'gov.collusion-report', 'label' => __('gov.collusion_title'),  'icon' => 'M12 9v3.75m0 0h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => '#ff4d7f'],
        ];
        @endphp
        @foreach($govLinks as $gl)
        <a href="{{ route($gl['route']) }}" class="bg-surface border border-th-border rounded-[12px] p-4 hover:bg-surface-2 transition-colors group">
            <div class="w-9 h-9 rounded-[10px] flex items-center justify-center mb-3" style="background: {{ $gl['color'] }}15; border: 1px solid {{ $gl['color'] }}30;">
                <svg class="w-[18px] h-[18px]" style="color: {{ $gl['color'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $gl['icon'] }}"/></svg>
            </div>
            <p class="text-[13px] font-semibold text-primary group-hover:text-accent transition-colors">{{ $gl['label'] }}</p>
        </a>
        @endforeach
    </div>
</div>

{{-- Monthly GMV trend --}}
@if(isset($gmvTrend) && $gmvTrend->count() > 0)
<div class="mt-8 bg-surface border border-th-border rounded-[16px] p-5">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.reports.monthly_trend') }}</h3>
    <div class="overflow-x-auto">
        <div class="flex items-end gap-2 h-[180px] min-w-[600px]">
            @php $maxGmv = $gmvTrend->max('value') ?: 1; @endphp
            @foreach($gmvTrend as $m)
                @php $pct = ($m->value / $maxGmv) * 100; @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-[9px] text-muted font-mono">{{ number_format($m->value / 1000) }}k</span>
                    <div class="w-full rounded-t-lg bg-accent/50" style="height: {{ max($pct, 2) }}%"></div>
                    <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month . '-01')->format('M') }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@endsection
