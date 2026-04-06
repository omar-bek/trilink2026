@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('rfq.title'))

@section('content')

<x-dashboard.page-header :title="__('rfq.title')" :subtitle="__('rfq.subtitle') . ' · Al-Ahram Group'" />

{{-- Top stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['all']"     :label="__('rfq.all')"        color="blue" />
    <x-dashboard.stat-card :value="$stats['open']"    :label="__('status.open')"    color="green" />
    <x-dashboard.stat-card :value="$stats['expired']" :label="__('status.expired')" color="red" />
    <x-dashboard.stat-card :value="$stats['closed']"  :label="__('status.closed')"  color="orange" />
    <x-dashboard.stat-card :value="$stats['draft']"   :label="__('status.draft')"   color="slate" />
</div>

{{-- Filters/sort --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center mb-4">
        <div class="flex-1 relative">
            <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search by title, ID, category..." class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
        </div>
        <select class="w-full lg:w-[160px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
            <option>Type</option>
        </select>
        <select class="w-full lg:w-[160px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
            <option>{{ __('rfq.category') }}</option>
        </select>
        <div class="flex items-center gap-2">
            <button class="w-10 h-10 rounded-xl bg-accent text-white flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5"/></svg></button>
            <button class="w-10 h-10 rounded-xl bg-page border border-th-border text-muted flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/></svg></button>
        </div>
        <span class="text-[12px] text-muted whitespace-nowrap">{{ __('rfq.found', ['count' => 12]) }}</span>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-[12px] text-muted">{{ __('rfq.sort_by') }}:</span>
        <button class="px-3 py-1 rounded-full text-[12px] font-medium text-accent bg-accent/10 border border-accent/20">{{ __('rfq.newest') }}</button>
        <button class="px-3 py-1 rounded-full text-[12px] font-medium text-muted hover:bg-surface-2">{{ __('rfq.deadline') }}</button>
        <button class="px-3 py-1 rounded-full text-[12px] font-medium text-muted hover:bg-surface-2">{{ __('rfq.value') }} ↓</button>
        <button class="px-3 py-1 rounded-full text-[12px] font-medium text-muted hover:bg-surface-2">{{ __('rfq.most_bids') }}</button>
    </div>
</div>

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('dashboard.purchase-requests.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
        {{ __('rfq.create') }}
    </a>
    <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        {{ __('common.export') }}
    </button>
</div>

{{-- RFQs list --}}
<div class="space-y-4">
    @foreach($rfqs as $rfq)
    <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-lg transition-all">
        <div class="flex items-start justify-between gap-4 mb-3">
            <span class="text-[12px] font-mono text-muted">#{{ $rfq['id'] }}</span>
            <x-dashboard.status-badge :status="$rfq['status']" />
        </div>

        <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['id']]) }}">
            <h3 class="text-[18px] font-bold text-accent mb-3 hover:underline">{{ $rfq['title'] }}</h3>
        </a>

        <div class="flex items-center gap-2 mb-3 flex-wrap">
            @foreach($rfq['tags'] as $i => $tag)
            @php $color = $rfq['tag_colors'][$i] ?? 'slate'; @endphp
            <span class="text-[10px] font-medium px-2.5 py-1 rounded-full {{ $color === 'blue' ? 'text-accent bg-accent/10 border border-accent/20' : 'text-muted bg-surface-2 border border-th-border' }}">{{ $tag }}</span>
            @endforeach
        </div>

        <p class="text-[13px] text-muted mb-5 leading-relaxed">{{ $rfq['desc'] }}</p>

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-5 text-[12px] text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25"/></svg>
                    {{ __('rfq.items', ['count' => $rfq['items']]) }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-[#10B981] font-bold text-[14px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101"/></svg>
                    {{ $rfq['amount'] }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                    {{ $rfq['date'] }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75"/></svg>
                    {{ __('rfq.bids', ['count' => $rfq['bids']]) }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard.bids') }}" class="text-[12px] font-semibold text-muted hover:text-accent transition-colors">{{ __('rfq.view_bids', ['count' => $rfq['bids']]) }}</a>
                <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['id']]) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
                    {{ __('rfq.manage') }}
                    <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>

@endsection
