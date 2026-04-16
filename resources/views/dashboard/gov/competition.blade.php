@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.competition_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.competition_title')" :subtitle="__('gov.competition_subtitle')" :back="route('gov.index')" />

{{-- Key competition indicators --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($totalRfqs)" :label="__('gov.total_rfqs')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>' />
    <x-dashboard.stat-card :value="number_format($singleBidRfqs)" :label="__('gov.single_bid_rfqs')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>' />
    <x-dashboard.stat-card :value="number_format($noBidRfqs)" :label="__('gov.no_bid_rfqs')" color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>' />
    <x-dashboard.stat-card :value="$avgBidsPerRfq" :label="__('gov.avg_bids_per_rfq')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z"/>' />
</div>

{{-- Competition health --}}
@php $singleBidPct = $totalRfqs > 0 ? round(($singleBidRfqs / $totalRfqs) * 100, 1) : 0; @endphp
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('gov.competition_health') }}</h3>
    <div class="flex items-center gap-4 mb-2">
        <div class="flex-1 h-4 bg-page rounded-full overflow-hidden">
            <div class="h-full rounded-full {{ $singleBidPct > 30 ? 'bg-[#ff4d7f]' : ($singleBidPct > 15 ? 'bg-[#ffb020]' : 'bg-[#00d9b5]') }}" style="width: {{ $singleBidPct }}%"></div>
        </div>
        <span class="text-[14px] font-bold {{ $singleBidPct > 30 ? 'text-[#ff4d7f]' : ($singleBidPct > 15 ? 'text-[#ffb020]' : 'text-[#00d9b5]') }}">{{ $singleBidPct }}%</span>
    </div>
    <p class="text-[12px] text-muted">{{ __('gov.single_bid_explanation') }}</p>
</div>

{{-- Market Concentration --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.market_concentration') }}</h3>
        <div class="space-y-3">
            @foreach($topSuppliers as $ts)
            @php $share = $totalMarketValue > 0 ? round(($ts->total_value / $totalMarketValue) * 100, 1) : 0; @endphp
            <div class="flex items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary truncate">{{ $ts->name }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 h-2 bg-page rounded-full overflow-hidden">
                            <div class="h-full bg-accent rounded-full" style="width: {{ min($share, 100) }}%"></div>
                        </div>
                        <span class="text-[11px] text-muted flex-shrink-0">{{ $share }}%</span>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <p class="text-[13px] font-bold text-primary">AED {{ number_format($ts->total_value) }}</p>
                    <p class="text-[11px] text-muted">{{ $ts->contract_count }} contracts</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- By Category --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.by_category') }}</h3>
        <div class="space-y-3">
            @php $maxCat = $byCategory->max('rfq_count') ?: 1; @endphp
            @foreach($byCategory as $cat)
            <div class="flex items-center justify-between gap-3">
                <span class="text-[13px] text-primary truncate flex-1">{{ $cat->name }}</span>
                <div class="w-32 h-2 bg-page rounded-full overflow-hidden flex-shrink-0">
                    <div class="h-full bg-[#8B5CF6] rounded-full" style="width: {{ ($cat->rfq_count / $maxCat) * 100 }}%"></div>
                </div>
                <span class="text-[13px] font-bold text-primary min-w-[30px] text-right">{{ $cat->rfq_count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
