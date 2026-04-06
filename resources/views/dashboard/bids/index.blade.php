@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('bids.title'))

@section('content')

<x-dashboard.page-header :title="__('bids.title')" :subtitle="__('bids.subtitle')">
    <x-slot:actions>
        <a href="{{ route('dashboard.rfqs') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
            {{ __('bids.view_rfqs') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

{{-- Search --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="{{ __('bids.search_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
    </div>
    <select class="w-full lg:w-[160px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
        <option>{{ __('common.status') }}</option>
    </select>
    <button class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-medium text-primary bg-page border border-th-border hover:bg-surface-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432"/></svg>
        {{ __('pr.more_filters') }}
    </button>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"        :label="__('bids.total')"        color="purple" />
    <x-dashboard.stat-card :value="$stats['under_review']" :label="__('bids.under_review')" color="orange" />
    <x-dashboard.stat-card :value="$stats['shortlisted']"  :label="__('bids.shortlisted')"  color="blue" />
    <x-dashboard.stat-card :value="$stats['accepted']"     :label="__('bids.accepted')"     color="green" />
    <x-dashboard.stat-card :value="$stats['rejected']"     :label="__('bids.rejected')"     color="red" />
</div>

{{-- Bids list --}}
<div class="space-y-4">
    @foreach($bids as $bid)
    <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 transition-all">
        <div class="flex items-start justify-between gap-4 mb-3 flex-wrap">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-[12px] font-mono text-muted">{{ $bid['id'] }}</span>
                <x-dashboard.status-badge :status="$bid['status']" />
                @if($bid['shortlisted'])
                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-[#F59E0B] bg-[#F59E0B]/10 border border-[#F59E0B]/20 rounded-full px-2.5 py-0.5">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    {{ __('bids.shortlisted') }}
                </span>
                @endif
            </div>
            <div class="text-end">
                <p class="text-[24px] font-bold text-accent">{{ $bid['amount'] }}</p>
                <div class="flex items-center justify-end gap-2 mt-0.5">
                    <span class="text-[11px] text-muted line-through">{{ $bid['old_amount'] }}</span>
                    <span class="text-[11px] font-bold {{ !empty($bid['price_up']) ? 'text-[#EF4444]' : 'text-[#10B981]' }} inline-flex items-center gap-0.5">
                        @if(!empty($bid['price_up']))<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 5l8 8H4z"/></svg>@else<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 19l-8-8h16z"/></svg>@endif
                        {{ $bid['diff'] }}%
                    </span>
                </div>
            </div>
        </div>

        <p class="text-[11px] text-muted mb-1">{{ $bid['rfq'] }} · {{ $bid['rfq_title'] }}</p>
        <h3 class="text-[18px] font-bold text-accent mb-3">Supplier {{ $bid['supplier'] }}</h3>

        <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
            <div class="flex items-center gap-5 text-[12px] text-muted flex-wrap">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-[#F59E0B]" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    {{ $bid['rating'] }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952"/></svg>
                    {{ $bid['received'] }} bids received
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                    {{ __('bids.submitted_on', ['date' => $bid['submitted']]) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ __('bids.expires_on', ['date' => $bid['expires']]) }}
                </span>
            </div>
            <div class="text-end text-[11px] text-muted">
                {{ __('bids.delivery_days', ['days' => $bid['days']]) }}<br>
                {{ $bid['terms'] }}
            </div>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('dashboard.bids.show', ['id' => $bid['numeric_id']]) }}" class="flex-1 min-w-[160px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5"/></svg>
                {{ __('common.view_details') }}
            </a>
            @if($bid['show_actions'])
            @can('bid.accept')
            <form method="POST" action="{{ route('dashboard.bids.accept', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#10B981] hover:bg-[#0EA371]">{{ __('bids.accept') }}</button>
            </form>
            @endcan
            @can('bid.withdraw')
            <form method="POST" action="{{ route('dashboard.bids.withdraw', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-[#EF4444] bg-[#EF4444]/10 border border-[#EF4444]/20 hover:bg-[#EF4444]/15">{{ __('bids.reject') }}</button>
            </form>
            @endcan
            @endif
        </div>
    </div>
    @endforeach
</div>

@endsection
