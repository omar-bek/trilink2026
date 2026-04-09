@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.my_contracts'))

@php
$statusPills = [
    'active'    => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'label' => __('contracts.manufacturing')],
    'pending'   => ['bg' => 'bg-[rgba(79,124,255,0.1)]', 'border' => 'border-[rgba(79,124,255,0.2)]', 'text' => 'text-[#4f7cff]', 'label' => __('contracts.in_production')],
    'completed' => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]',  'text' => 'text-[#00d9b5]', 'label' => __('status.completed')],
    'closed'    => ['bg' => 'bg-[rgba(180,182,192,0.1)]','border' => 'border-[rgba(180,182,192,0.2)]','text' => 'text-[#b4b6c0]', 'label' => __('status.closed')],
];

$statCards = [
    ['value' => $stats['active'],            'label' => __('dashboard.active_contracts'), 'color' => 'text-[#4f7cff]'],
    ['value' => $stats['completed'],         'label' => __('status.completed'),           'color' => 'text-[#00d9b5]'],
    ['value' => $stats['total_value'],       'label' => __('contracts.total_value'),      'color' => 'text-[#ffb020]'],
    ['value' => $stats['avg_progress'] . '%','label' => __('contracts.avg_progress'),     'color' => 'text-[#8b5cf6]'],
];
@endphp

@section('content')

{{-- Header --}}
<div class="mb-6">
    <h1 class="text-[28px] sm:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ __('contracts.my_contracts') }}</h1>
    <p class="text-[16px] text-[#b4b6c0] mt-1">{{ __('contracts.my_contracts_subtitle') }}</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @foreach($statCards as $card)
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold {{ $card['color'] }} leading-[32px] tracking-[0.003em] truncate">{{ $card['value'] }}</p>
        <p class="text-[14px] text-[#b4b6c0] leading-[20px] mt-1">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- Search + filters. The previous version of this row had an empty
     placeholder div where the status filter was meant to go — replaced
     with a real status select + sort dropdown so the supplier can
     actually filter their backlog. --}}
<form method="GET" action="{{ route('dashboard.contracts') }}"
      class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-[#b4b6c0] absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('contracts.search_placeholder') }}"
               aria-label="{{ __('common.search') }}"
               class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-white placeholder:text-[rgba(255,255,255,0.5)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
    </div>
    <select name="status" onchange="this.form.submit()"
            aria-label="{{ __('contracts.all_status') }}"
            class="w-full lg:w-[180px] bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] px-4 h-12 text-[14px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
        <option value="all"       @selected(request('status', 'all') === 'all')>{{ __('contracts.all_status') }}</option>
        <option value="active"    @selected(request('status') === 'active')>{{ __('contracts.active') }}</option>
        <option value="pending"   @selected(request('status') === 'pending')>{{ __('status.pending') }}</option>
        <option value="completed" @selected(request('status') === 'completed')>{{ __('contracts.completed') }}</option>
        <option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('status.cancelled') }}</option>
    </select>
    <select name="sort" onchange="this.form.submit()"
            aria-label="{{ __('contracts.sort_newest') }}"
            class="w-full lg:w-[180px] bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] px-4 h-12 text-[14px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
        <option value="newest"      @selected(request('sort', 'newest') === 'newest')>{{ __('contracts.sort_newest') }}</option>
        <option value="oldest"      @selected(request('sort') === 'oldest')>{{ __('contracts.sort_oldest') }}</option>
        <option value="value_desc"  @selected(request('sort') === 'value_desc')>{{ __('contracts.sort_value_desc') }}</option>
        <option value="value_asc"   @selected(request('sort') === 'value_asc')>{{ __('contracts.sort_value_asc') }}</option>
        <option value="ending_soon" @selected(request('sort') === 'ending_soon')>{{ __('contracts.sort_ending_soon') }}</option>
    </select>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
        {{ __('common.search') }}
    </button>
</form>

{{-- Tabs --}}
<div x-data="{ tab: 'active' }" class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
    <div class="grid grid-cols-2 border-b border-[rgba(255,255,255,0.1)] mb-6 -mx-[25px] px-[25px]">
        <button type="button" @click="tab = 'active'"
                :class="tab === 'active' ? 'text-[#4f7cff] border-[#4f7cff]' : 'text-[#b4b6c0] border-transparent hover:text-white'"
                class="pb-3 text-[14px] font-medium border-b-2 transition-colors text-center">
            {{ __('dashboard.active_contracts') }} ({{ count($active_contracts) }})
        </button>
        <button type="button" @click="tab = 'completed'"
                :class="tab === 'completed' ? 'text-[#4f7cff] border-[#4f7cff]' : 'text-[#b4b6c0] border-transparent hover:text-white'"
                class="pb-3 text-[14px] font-medium border-b-2 transition-colors text-center">
            {{ __('status.completed') }} ({{ count($completed_contracts) }})
        </button>
    </div>

    {{-- Active tab --}}
    <div x-show="tab === 'active'" x-cloak class="space-y-4">
        @forelse($active_contracts as $c)
        @php $pill = $statusPills[$c['status']] ?? $statusPills['active']; @endphp
        <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-5 hover:border-[#4f7cff]/40 transition-colors">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <span class="text-[13px] text-[#b4b6c0]">{{ $c['id'] }} · {{ $c['rfq_ref'] }}</span>
                        <span class="inline-flex items-center h-6 px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                            {{ $c['status_label'] ?: $pill['label'] }}
                        </span>
                    </div>
                    <p class="text-[18px] font-semibold text-white leading-[26px]">{{ $c['title'] }}</p>
                    <p class="text-[13px] text-[#b4b6c0] mt-1">{{ __('bids.buyer') }}: <span class="text-white">{{ $c['buyer'] }}</span></p>
                </div>
                <div class="text-end">
                    <p class="text-[22px] font-semibold text-[#00d9b5] leading-[30px]">{{ $c['amount'] }}</p>
                    <p class="text-[12px] text-[#b4b6c0]">{{ __('contracts.contract_value') }}</p>
                </div>
            </div>

            {{-- Progress row --}}
            <div class="mb-4">
                <div class="flex items-center justify-between text-[12px] mb-2">
                    <span class="text-[#b4b6c0]">{{ $c['status_label'] ?: $pill['label'] }}</span>
                    <span class="text-white font-medium">{{ $c['progress'] }}% {{ __('common.complete') }}</span>
                </div>
                <div class="w-full h-2 bg-[#252932] rounded-full overflow-hidden">
                    <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $c['progress'] }}%"></div>
                </div>
            </div>

            {{-- 4-col meta row --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 pb-4 border-b border-[rgba(255,255,255,0.08)]">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.started') }}</p><p class="text-[13px] text-white">{{ $c['started'] }}</p></div>
                </div>
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.delivery') }}</p><p class="text-[13px] text-white">{{ $c['expected'] }}</p></div>
                </div>
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.days_left') }}</p><p class="text-[13px] text-white">{{ $c['days_left'] !== null ? $c['days_left'] . ' ' . __('common.days') : '—' }}</p></div>
                </div>
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.5-4-3.5S9.79 5 12 5c1.128 0 2.147.373 2.854.968l.875.675"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.received') }}</p><p class="text-[13px] text-[#00d9b5] font-medium">{{ $c['received'] }}</p></div>
                </div>
            </div>

            {{-- Bottom row: Pending Payment + View Details --}}
            <div class="flex items-center justify-between gap-3 mt-4 flex-wrap">
                <p class="text-[13px] text-[#b4b6c0]">{{ __('contracts.pending_payment') }}: <span class="text-[#ffb020] font-medium">{{ $c['pending'] }}</span></p>
                <a href="{{ route('dashboard.contracts.show', ['id' => $c['numeric_id']]) }}"
                   class="inline-flex items-center h-10 px-4 rounded-[12px] text-[13px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
                    {{ __('common.view_details') }}
                </a>
            </div>
        </div>
        @empty
        <p class="text-[14px] text-[#b4b6c0] text-center py-12">{{ __('contracts.no_active') }}</p>
        @endforelse
    </div>

    {{-- Completed tab --}}
    <div x-show="tab === 'completed'" x-cloak class="space-y-4">
        @forelse($completed_contracts as $c)
        <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <span class="text-[13px] text-[#b4b6c0]">{{ $c['id'] }} · {{ $c['rfq_ref'] }}</span>
                        <span class="inline-flex items-center h-6 px-3 rounded-full border bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">{{ __('status.completed') }}</span>
                    </div>
                    <p class="text-[18px] font-semibold text-white leading-[26px]">{{ $c['title'] }}</p>
                    <p class="text-[13px] text-[#b4b6c0] mt-1">{{ __('bids.buyer') }}: <span class="text-white">{{ $c['buyer'] }}</span></p>
                </div>
                <div class="text-end">
                    <p class="text-[22px] font-semibold text-[#00d9b5] leading-[30px]">{{ $c['amount'] }}</p>
                    <p class="text-[12px] text-[#b4b6c0]">{{ __('contracts.contract_value') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 pt-3 border-t border-[rgba(255,255,255,0.08)]">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.started') }}</p><p class="text-[13px] text-white">{{ $c['started'] }}</p></div>
                </div>
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#00d9b5] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('status.completed') }}</p><p class="text-[13px] text-white">{{ $c['completed_at'] }}</p></div>
                </div>
                @if($c['rating'])
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#ffb020] mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('bids.rating') }}</p><p class="text-[13px] text-white">{{ number_format($c['rating'], 1) }} <span class="text-[#ffb020]">★</span></p></div>
                </div>
                @endif
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-[#b4b6c0] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.5-4-3.5S9.79 5 12 5c1.128 0 2.147.373 2.854.968l.875.675"/></svg>
                    <div><p class="text-[11px] text-[#b4b6c0]">{{ __('contracts.received') }}</p><p class="text-[13px] text-[#00d9b5] font-medium">{{ $c['received'] }}</p></div>
                </div>
            </div>
        </div>
        @empty
        <p class="text-[14px] text-[#b4b6c0] text-center py-12">{{ __('contracts.no_completed') }}</p>
        @endforelse
    </div>

    @if(isset($paginator) && $paginator->hasPages())
    <div class="mt-6 pt-4 border-t border-[rgba(255,255,255,0.08)]">
        {{ $paginator->onEachSide(1)->links() }}
    </div>
    @endif
</div>

@endsection
