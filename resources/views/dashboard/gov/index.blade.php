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

@endsection
