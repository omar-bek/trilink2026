@extends('layouts.dashboard', ['active' => 'shipments'])
@section('title', __('shipments.title'))

@php
$companyName = auth()->user()?->company?->name;
$subtitle    = __('shipments.subtitle') . ($companyName ? ' · ' . $companyName : '');
@endphp

@section('content')

<x-dashboard.page-header :title="__('shipments.title')" :subtitle="$subtitle" :back="route('dashboard')" />

{{-- Stats — clickable; clicking a card filters the list to that status. --}}
@php
    $shipmentStatusCards = [
        ['key' => 'all',        'label' => __('shipments.total'),      'color' => 'blue',   'value' => $stats['total']],
        ['key' => 'in_transit', 'label' => __('shipments.in_transit'), 'color' => 'green',  'value' => $stats['in_transit']],
        ['key' => 'at_customs', 'label' => __('shipments.at_customs'), 'color' => 'orange', 'value' => $stats['at_customs']],
        ['key' => 'delayed',    'label' => __('shipments.delayed'),    'color' => 'red',    'value' => $stats['delayed']],
        ['key' => 'delivered',  'label' => __('shipments.delivered'),  'color' => 'purple', 'value' => $stats['delivered']],
    ];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($shipmentStatusCards as $card)
        <x-dashboard.stat-card
            :value="$card['value']"
            :label="$card['label']"
            :color="$card['color']"
            :href="route('dashboard.shipments', array_filter(['status' => $card['key'] === 'all' ? null : $card['key'], 'q' => $search ?: null]))"
            :active="$statusFilter === $card['key']" />
    @endforeach
</div>

{{-- Search + status filter --}}
<x-dashboard.filter-bar
    :action="route('dashboard.shipments')"
    :search="$search"
    :placeholder="__('shipments.search_placeholder')"
    :clearUrl="route('dashboard.shipments')"
    :hasFilters="$search !== '' || $statusFilter !== 'all'"
    :count="$resultCount"
    countLabel="shipments.found">
    <x-slot:filters>
        <select name="status"
                class="w-full lg:w-[200px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            <option value="all"        @selected($statusFilter === 'all')>{{ __('shipments.all_statuses') }}</option>
            <option value="preparing"  @selected($statusFilter === 'preparing')>{{ __('status.preparing') }}</option>
            <option value="in_transit" @selected($statusFilter === 'in_transit')>{{ __('shipments.in_transit') }}</option>
            <option value="at_customs" @selected($statusFilter === 'at_customs')>{{ __('shipments.at_customs') }}</option>
            <option value="delivered"  @selected($statusFilter === 'delivered')>{{ __('shipments.delivered') }}</option>
            <option value="delayed"    @selected($statusFilter === 'delayed')>{{ __('shipments.delayed') }}</option>
        </select>
    </x-slot:filters>
</x-dashboard.filter-bar>

<div class="space-y-4">
    @forelse($shipments as $sh)
    <a href="{{ route('dashboard.shipments.show', ['id' => $sh['numeric_id']]) }}" class="block bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-lg transition-all">
        <div class="flex items-start justify-between gap-4 mb-2 flex-wrap">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-[12px] font-mono text-muted">{{ $sh['id'] }}</span>
                <x-dashboard.status-badge :status="$sh['status']" />
            </div>
        </div>

        <h3 class="text-[18px] font-bold text-accent mb-1">{{ $sh['title'] }}</h3>
        <p class="text-[12px] text-muted mb-4">{{ __('disputes.contract') }}: {{ $sh['contract'] }}</p>

        <div class="flex items-center gap-3 text-[13px] mb-4">
            <span class="inline-flex items-center gap-1.5 text-muted">
                <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                {{ __('common.from') }}: <span class="font-semibold text-body">{{ $sh['from'] }}</span>
            </span>
            <svg class="w-4 h-4 text-muted rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
            <span class="inline-flex items-center gap-1.5 text-muted">
                <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                {{ __('common.to') }}: <span class="font-semibold text-body">{{ $sh['to'] }}</span>
            </span>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between text-[11px] mb-1">
                <span class="text-muted">{{ __('common.progress') }}</span>
                <span class="font-bold text-primary">{{ $sh['progress'] }}%</span>
            </div>
            <div class="w-full h-2 bg-elevated rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-accent to-[#00d9b5] rounded-full transition-all" style="width: {{ $sh['progress'] }}%"></div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap text-[11px]">
            <div class="flex items-center gap-5 text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ __('common.eta') }}: <span class="font-semibold text-body">{{ $sh['eta'] }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453.415 2.18.654A60.145 60.145 0 0118 30l-2.74 1.22m0 0l-5.94-2.28m5.94 2.28l-2.28-5.94"/></svg>
                    {{ __('common.carrier') }}: <span class="font-semibold text-body">{{ $sh['carrier'] }}</span>
                </span>
            </div>
            <span class="inline-flex items-center gap-1 text-muted">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
                {{ $sh['time'] }} {{ __('common.ago') }}
            </span>
        </div>
    </a>
    @empty
    @if($search !== '' || $statusFilter !== 'all')
        <x-dashboard.empty-state
            :title="__('shipments.no_results_title')"
            :message="__('shipments.no_results_message')"
            :cta="__('common.clear_filters')"
            :ctaUrl="route('dashboard.shipments')" />
    @else
        <x-dashboard.empty-state
            :title="__('shipments.empty_title')"
            :message="__('shipments.empty_message')" />
    @endif
    @endforelse
</div>

@endsection
