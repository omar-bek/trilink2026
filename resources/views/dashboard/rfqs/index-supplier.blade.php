@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('rfq.available_title') ?? 'Available RFQs')

@php
// Quick-filter pill definitions. Each renders as a link back to the same page
// with a ?filter=<key> query string — the controller reads this to scope the list.
$filters = [
    'all'        => __('rfq.filter_all') ?? 'All RFQs',
    'high_match' => __('rfq.filter_high_match') ?? 'High Match (90%+)',
    'urgent'     => __('rfq.filter_urgent') ?? 'Urgent (≤3 days)',
    'high_value' => __('rfq.filter_high_value') ?? 'High Value (100K+)',
    'submitted'  => __('rfq.filter_submitted'),
    'not_bid'    => __('rfq.filter_not_bid'),
];
@endphp

@section('content')

{{-- Header --}}
<div class="mb-6">
    <h1 class="text-[28px] sm:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ __('rfq.available_title') ?? 'Available RFQs' }}</h1>
    <p class="text-[16px] text-[#b4b6c0] mt-1">{{ __('rfq.available_subtitle') ?? 'Browse and bid on procurement opportunities' }}</p>
</div>

{{-- Search + quick filter pills --}}
<form method="GET" action="{{ route('dashboard.rfqs') }}"
      class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 mb-6">
    <input type="hidden" name="filter" value="{{ $filter }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
        <div class="relative lg:col-span-1">
            <svg class="w-4 h-4 text-[#b4b6c0] absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" name="q" value="{{ $query }}"
                   placeholder="{{ __('rfq.search_placeholder') ?? 'Search RFQs by title, buyer, or category...' }}"
                   class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] ps-11 pe-4 h-11 text-[14px] text-white placeholder:text-[rgba(255,255,255,0.5)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
        </div>
        {{-- Two reserved slots to match the Figma layout. Suppliers rarely need a status dropdown here since everything is "open", so these are kept visual placeholders for now. --}}
        <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] h-11"></div>
        <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] h-11"></div>
    </div>

    {{-- Filter pills --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach($filters as $key => $label)
            @php $active = $filter === $key; @endphp
            <a href="{{ route('dashboard.rfqs', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $query])) }}"
               class="inline-flex items-center h-9 px-4 rounded-full text-[13px] font-medium transition-colors {{ $active ? 'bg-[#4f7cff] text-white' : 'bg-[#0f1117] border border-[rgba(255,255,255,0.1)] text-[#b4b6c0] hover:text-white hover:border-[#4f7cff]/40' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</form>

{{-- Count + "new this week" + Save this search button --}}
<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <p class="text-[14px] text-[#b4b6c0]">Showing <span class="text-white font-semibold">{{ $total_count }}</span> RFQs</p>

    <div class="flex items-center gap-3 flex-wrap">
        @if($new_this_week > 0)
        <p class="text-[14px] text-[#00d9b5] inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941"/></svg>
            {{ $new_this_week }} new RFQs this week
        </p>
        @endif

        {{-- Save this search (Phase 1 / task 1.5). Snapshot the current
             ?q= and ?filter= as a saved search owned by the user — the
             daily digest job (task 1.6) reads it from there. --}}
        <form method="POST" action="{{ route('dashboard.saved-searches.store') }}"
              onsubmit="this.label.value = prompt('{{ __('saved_searches.name_prompt') }}', '{{ $query ?: ($filter ?: __('saved_searches.suggested_label')) }}'); return !!this.label.value;">
            @csrf
            <input type="hidden" name="resource_type" value="rfqs">
            <input type="hidden" name="label" value="">
            @if($query)
                <input type="hidden" name="filters[q]" value="{{ $query }}">
            @endif
            @if($filter && $filter !== 'all')
                <input type="hidden" name="filters[filter]" value="{{ $filter }}">
            @endif
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg text-[11px] font-semibold text-[#4f7cff] bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.2)] hover:bg-[rgba(79,124,255,0.2)]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                {{ __('saved_searches.save_this_search') }}
            </button>
        </form>
    </div>
</div>

{{-- RFQ cards --}}
<div class="space-y-4">
    @forelse($rfqs as $rfq)
    @php $hasMyBid = !empty($rfq['my_bid_id']); @endphp
    <div class="relative bg-[#1a1d29] border {{ $hasMyBid ? 'border-[#00d9b5]/40' : 'border-[rgba(255,255,255,0.1)]' }} rounded-[16px] p-[25px] hover:border-[#4f7cff]/40 transition-colors">

        {{-- Submitted ribbon: a corner indicator that's visible without
             scanning the badge row. Only renders when this supplier has a
             bid on the RFQ. --}}
        @if($hasMyBid)
        <div class="absolute top-0 end-0 ps-4 pe-4 py-1.5 rounded-bl-[12px] rounded-tr-[16px] bg-[#00d9b5] text-[#0a0d14] text-[11px] font-bold uppercase tracking-wider inline-flex items-center gap-1.5 shadow-[0_4px_14px_rgba(0,217,181,0.35)]">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ __('rfq.bid_submitted') }}
        </div>
        @endif

        {{-- Top: id + status + match + optional urgent pill (left) / AED amount (right) --}}
        <div class="flex items-start justify-between gap-4 flex-wrap mb-3 {{ $hasMyBid ? 'mt-6' : '' }}">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-[14px] text-[#b4b6c0]">#{{ $rfq['id'] }}</span>
                <span class="inline-flex items-center gap-2 h-[26px] px-3 rounded-full border bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                    {{ __('common.open') }}
                </span>
                <span class="inline-flex items-center gap-1.5 h-[26px] px-3 rounded-full border bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519"/></svg>
                    {{ __('rfq.match_percent', ['percent' => $rfq['match']]) }}
                </span>
                @if($rfq['urgent'])
                <span class="inline-flex items-center gap-1.5 h-[26px] px-3 rounded-full border bg-[rgba(255,77,127,0.1)] border-[rgba(255,77,127,0.2)] text-[#ff4d7f] text-[12px] font-medium">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/></svg>
                    {{ __('rfq.urgent') }}
                </span>
                @endif
            </div>
            <div class="text-end">
                <p class="text-[24px] font-semibold text-[#00d9b5] leading-[32px] tracking-[0.003em]">{{ $rfq['amount'] }}</p>
                <p class="text-[12px] text-[#b4b6c0]">{{ __('rfq.budget') }}</p>
            </div>
        </div>

        {{-- Title + Buyer --}}
        <h3 class="text-[20px] font-semibold text-white leading-[28px] tracking-[-0.022em] mb-1">{{ $rfq['title'] }}</h3>
        <div class="flex items-center gap-2 flex-wrap mb-4">
            <p class="text-[14px] text-[#b4b6c0]">{{ __('bids.buyer') }}: <span class="text-white">{{ $rfq['buyer'] }}</span></p>
            {{-- Phase 2 / Sprint 8 / task 2.8 — buyer's verification tier on
                 every available-RFQ row, so suppliers can prioritise bids on
                 trustworthy buyers. --}}
            @if(!empty($rfq['buyer_verification_level']))
                <x-dashboard.verification-badge :level="$rfq['buyer_verification_level']" />
            @endif
        </div>

        {{-- 4-column meta row: Deadline / Location / Quantity / Bidders --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 pb-4 border-b border-[rgba(255,255,255,0.1)]">
            <div class="flex items-start gap-2.5">
                <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[12px] text-[#b4b6c0]">Deadline</p>
                    <p class="text-[14px] text-white">{{ $rfq['deadline'] }}
                        @if($rfq['days_left'] !== null)
                            <span class="{{ $rfq['days_left'] <= 3 ? 'text-[#ff4d7f]' : 'text-[#b4b6c0]' }}">({{ $rfq['days_left'] }}d left)</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[12px] text-[#b4b6c0]">Location</p>
                    <p class="text-[14px] text-white truncate">{{ $rfq['location'] }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[12px] text-[#b4b6c0]">Quantity</p>
                    <p class="text-[14px] text-white truncate">{{ $rfq['quantity'] }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[12px] text-[#b4b6c0]">Bidders</p>
                    <p class="text-[14px] text-white">{{ $rfq['bidders'] }} submitted</p>
                </div>
            </div>
        </div>

        {{-- Bottom: category pill + actions --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center h-7 px-3 rounded-full bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.2)] text-[#4f7cff] text-[12px] font-medium">
                    {{ $rfq['category'] }}
                </span>
                @if($hasMyBid)
                <span class="inline-flex items-center gap-1.5 h-7 px-3 rounded-full bg-[rgba(0,217,181,0.1)] border border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">
                    {{ __('rfq.your_bid') }}: {{ $rfq['my_bid_amount'] }}
                </span>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}"
                   class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[14px] font-medium {{ $hasMyBid ? 'text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40' : 'text-white bg-[#4f7cff] hover:bg-[#6b91ff]' }} transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('common.view_details') }}
                </a>
                @if($hasMyBid)
                <a href="{{ route('dashboard.bids.show', ['id' => $rfq['my_bid_id']]) }}"
                   class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('rfq.view_my_bid') }}
                </a>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-12 text-center text-[14px] text-[#b4b6c0]">
        {{ __('common.no_data') }}
    </div>
    @endforelse
</div>

@endsection
