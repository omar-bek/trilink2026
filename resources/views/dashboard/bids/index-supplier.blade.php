@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('supplier.my_bids'))

@php
// Status pills — labels are translation keys so they auto-localise to AR
// in RTL mode without hardcoded English bleeding through.
$statusPills = [
    'submitted'    => ['bg' => 'bg-accent/10', 'border' => 'border-accent/20', 'text' => 'text-accent', 'dot' => 'bg-accent', 'label' => __('status.under_review')],
    'under_review' => ['bg' => 'bg-accent/10', 'border' => 'border-accent/20', 'text' => 'text-accent', 'dot' => 'bg-accent', 'label' => __('status.under_review')],
    'shortlisted'  => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]', 'label' => __('status.shortlisted')],
    'accepted'     => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]', 'label' => __('supplier.won')],
    'rejected'     => ['bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/20', 'text' => 'text-[#ff4d7f]', 'dot' => 'bg-[#ff4d7f]', 'label' => __('supplier.lost')],
    'draft'        => ['bg' => 'bg-muted/10', 'border' => 'border-muted/20', 'text' => 'text-muted', 'dot' => 'bg-muted', 'label' => __('status.draft')],
    'negotiation'  => ['bg' => 'bg-[#ffb020]/10', 'border' => 'border-[#ffb020]/20', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]', 'label' => __('status.negotiation')],
];

$statCards = [
    ['value' => $stats['active'],         'label' => __('supplier.active_bids'), 'color' => 'text-accent'],
    ['value' => $stats['won'],            'label' => __('supplier.won'),         'color' => 'text-[#00d9b5]'],
    ['value' => $stats['lost'],           'label' => __('supplier.lost'),        'color' => 'text-[#ff4d7f]'],
    ['value' => $stats['total_value'],    'label' => __('contracts.total_value'),'color' => 'text-[#ffb020]'],
    ['value' => $stats['win_rate'] . '%', 'label' => __('supplier.win_rate'),    'color' => 'text-[#8b5cf6]'],
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('supplier.my_bids') }}</h1>
        <p class="text-[16px] text-muted mt-1">{{ __('supplier.my_bids_subtitle') }}</p>
    </div>
    <a href="{{ route('dashboard.rfqs') }}"
       class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25"/></svg>
        {{ __('bids.browse_rfqs') }}
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($statCards as $card)
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold {{ $card['color'] }} leading-[32px] tracking-[0.003em] truncate">{{ $card['value'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- Search --}}
<form method="GET" action="{{ route('dashboard.bids') }}"
      class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('supplier.bids_search_placeholder') }}"
               class="w-full bg-page border border-th-border rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
    </div>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        {{ __('common.search') }}
    </button>
</form>

{{-- Tabs --}}
<div x-data="{ tab: 'active' }" class="bg-surface border border-th-border rounded-[16px] p-[25px]">
    <div class="grid grid-cols-3 border-b border-th-border mb-6 -mx-[25px] px-[25px]">
        @php
            $tabs = [
                'active' => ['label' => __('supplier.active_bids'), 'count' => count($active_bids)],
                'won'    => ['label' => __('supplier.won'),         'count' => count($won_bids)],
                'lost'   => ['label' => __('supplier.lost'),        'count' => count($lost_bids)],
            ];
        @endphp
        @foreach($tabs as $key => $t)
        <button type="button" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-primary'"
                class="pb-3 text-[14px] font-medium border-b-2 transition-colors text-center">
            {{ $t['label'] }} ({{ $t['count'] }})
        </button>
        @endforeach
    </div>

    @foreach(['active' => $active_bids, 'won' => $won_bids, 'lost' => $lost_bids] as $tabKey => $list)
    <div x-show="tab === '{{ $tabKey }}'" x-cloak class="space-y-3">
        @forelse($list as $bid)
        @php $pill = $statusPills[$bid['status']] ?? $statusPills['draft']; @endphp
        <div class="bg-page border border-th-border rounded-[12px] p-5 hover:border-accent/40 transition-colors">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <span class="text-[13px] text-muted">{{ $bid['id'] }} · #{{ $bid['rfq'] }}</span>
                        <span class="inline-flex items-center gap-1.5 h-5 px-2 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[11px] font-medium">
                            <span class="w-1 h-1 rounded-full {{ $pill['dot'] }}"></span>
                            {{ $pill['label'] }}
                        </span>
                    </div>
                    <a href="{{ route('dashboard.bids.show', ['id' => $bid['numeric_id']]) }}"
                       class="text-[16px] font-semibold text-primary leading-[22px] hover:text-accent transition-colors">{{ $bid['rfq_title'] }}</a>
                    <p class="text-[13px] text-muted mt-1">{{ __('bids.buyer') }}: <span class="text-primary">{{ $bid['buyer'] }}</span></p>
                </div>
                <div class="text-end flex-shrink-0">
                    <p class="text-[20px] font-semibold text-[#00d9b5] leading-[28px]">{{ $bid['amount'] }}</p>
                    <p class="text-[12px] text-muted">{{ __('bids.bid_amount') }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 mt-4 pt-4 border-t border-th-border flex-wrap">
                <div class="inline-flex items-center gap-2 text-[13px] text-muted">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    {{ __('bids.submitted_label') }}: <span class="text-primary font-medium">{{ $bid['submitted'] }}</span>
                    <span class="text-faint">({{ $bid['ago'] }} {{ __('common.ago') }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard.bids.show', ['id' => $bid['numeric_id']]) }}"
                       class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] text-[13px] font-medium text-accent bg-accent/10 border border-accent/20 hover:bg-accent/15 transition-colors">
                        {{ __('common.view') }}
                    </a>
                    @if($bid['can_withdraw'])
                    <form method="POST" action="{{ route('dashboard.bids.withdraw', ['id' => $bid['numeric_id']]) }}"
                          onsubmit="return confirm('{{ __('bids.withdraw_confirm') }}')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] text-[13px] font-medium text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/15 transition-colors">
                            {{ __('bids.withdraw_bid') }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="bg-page border border-th-border rounded-[12px] p-12 text-center">
            <svg class="w-12 h-12 text-faint mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25"/></svg>
            <p class="text-[14px] text-muted">{{ __('supplier.no_bids_in_category') }}</p>
        </div>
        @endforelse
    </div>
    @endforeach
</div>

@endsection
