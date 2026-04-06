@extends('layouts.dashboard', ['active' => 'shipments'])
@section('title', __('shipments.title'))

@section('content')

<x-dashboard.page-header :title="__('shipments.title')" :subtitle="__('shipments.subtitle') . ' · Al-Ahram Group'" :back="route('dashboard')" />

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"      :label="__('shipments.total')"      color="blue" />
    <x-dashboard.stat-card :value="$stats['in_transit']" :label="__('shipments.in_transit')" color="green" />
    <x-dashboard.stat-card :value="$stats['at_customs']" :label="__('shipments.at_customs')" color="orange" />
    <x-dashboard.stat-card :value="$stats['delayed']"    :label="__('shipments.delayed')"    color="red" />
    <x-dashboard.stat-card :value="$stats['delivered']"  :label="__('shipments.delivered')"  color="purple" />
</div>

{{-- Search bar --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="{{ __('shipments.search_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
    </div>
    <select class="w-full lg:w-[200px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
        <option>All Statuses</option>
    </select>
    <span class="text-[12px] text-muted whitespace-nowrap">{{ __('shipments.found', ['count' => 5]) }}</span>
</div>

<div class="space-y-4">
    @foreach($shipments as $sh)
    <a href="{{ route('dashboard.shipments.show', ['id' => $sh['id']]) }}" class="block bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-lg transition-all">
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
                <div class="h-full bg-gradient-to-r from-accent to-[#10B981] rounded-full transition-all" style="width: {{ $sh['progress'] }}%"></div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap text-[11px]">
            <div class="flex items-center gap-5 text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ __('common.eta') }}: <span class="font-semibold text-body">{{ $sh['eta'] }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101"/></svg>
                    {{ __('common.carrier') }}: <span class="font-semibold text-body">{{ $sh['carrier'] }}</span>
                </span>
            </div>
            <span class="inline-flex items-center gap-1 text-muted">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
                {{ $sh['time'] }} {{ __('common.ago') }}
            </span>
        </div>
    </a>
    @endforeach
</div>

@endsection
