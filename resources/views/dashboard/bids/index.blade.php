@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('bids.title'))

@php
// Status pill colors — bg color@10%, border color@20%, text solid. Same pattern
// as the Purchase Requests index so the two screens feel part of one system.
$statusPills = [
    'submitted'    => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'shortlisted'  => ['bg' => 'bg-accent/10',     'border' => 'border-accent/20',     'text' => 'text-accent',     'dot' => 'bg-accent'],
    'under_review' => ['bg' => 'bg-[#ffb020]/10', 'border' => 'border-[#ffb020]/20', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]'],
    'accepted'     => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'rejected'     => ['bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/20', 'text' => 'text-[#ff4d7f]', 'dot' => 'bg-[#ff4d7f]'],
    'draft'        => ['bg' => 'bg-muted/10',       'border' => 'border-muted/20',       'text' => 'text-muted',       'dot' => 'bg-muted'],
];

$statColors = [
    'purple' => 'text-[#8b5cf6]',
    'orange' => 'text-[#ffb020]',
    'blue'   => 'text-accent',
    'green'  => 'text-[#00d9b5]',
    'red'    => 'text-[#ff4d7f]',
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('bids.title') }}</h1>
        <p class="text-[16px] text-muted mt-1">{{ __('bids.subtitle') }}</p>

        {{-- Company-centric view switcher: received vs submitted bids.
             Hidden when the controller didn't compute tab counts (legacy
             callers / users with no company_id), in which case the page
             behaves exactly as it did before. --}}
        @if(!empty($tabCounts))
        <div class="mt-4 bg-surface border border-th-border rounded-2xl p-1.5 inline-flex gap-1">
            @php
                $bidTabs = [
                    ['key' => 'received',  'label' => __('bids.tab_received'),  'count' => $tabCounts['received']],
                    ['key' => 'submitted', 'label' => __('bids.tab_submitted'), 'count' => $tabCounts['submitted']],
                ];
            @endphp
            @foreach($bidTabs as $tab)
            @php $isActive = ($activeTab ?? 'received') === $tab['key']; @endphp
            <a href="{{ route('dashboard.bids', ['tab' => $tab['key']]) }}"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-[13px] font-semibold transition-colors {{ $isActive ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.25)]' : 'text-muted hover:text-primary hover:bg-surface-2' }}">
                {{ $tab['label'] }}
                <span class="inline-flex items-center justify-center min-w-[22px] h-[20px] px-1.5 rounded-full text-[11px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-page text-muted' }}">{{ $tab['count'] }}</span>
            </a>
            @endforeach
        </div>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <x-dashboard.export-csv-button :url="route('dashboard.bids') . '?' . http_build_query(array_merge(request()->query(), ['export' => 'csv']))" />
        <a href="{{ route('dashboard.rfqs') }}"
           class="inline-flex items-center gap-2 px-5 h-12 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            {{ __('bids.view_rfqs') }}
        </a>
    </div>
</div>

{{-- Stats --}}
@php
$statCards = [
    ['value' => $stats['total'],        'label' => __('bids.total'),        'color' => 'purple'],
    ['value' => $stats['under_review'], 'label' => __('bids.under_review'), 'color' => 'orange'],
    ['value' => $stats['shortlisted'],  'label' => __('bids.shortlisted'),  'color' => 'blue'],
    ['value' => $stats['accepted'],     'label' => __('bids.accepted'),     'color' => 'green'],
    ['value' => $stats['rejected'],     'label' => __('bids.rejected'),     'color' => 'red'],
];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($statCards as $card)
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold {{ $statColors[$card['color']] }} leading-[32px] tracking-[0.003em]">{{ $card['value'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- Search bar --}}
<form method="GET" action="{{ route('dashboard.bids') }}"
      class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('bids.search_placeholder') }}"
               class="w-full bg-page border border-th-border rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
    </div>
    <select name="status"
            class="w-full lg:w-[180px] bg-page border border-th-border rounded-[12px] px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 cursor-pointer">
        <option value="">{{ __('common.status') }}</option>
        <option value="submitted"    @selected(request('status') === 'submitted')>{{ __('bids.submitted') ?? 'Submitted' }}</option>
        <option value="under_review" @selected(request('status') === 'under_review')>{{ __('bids.under_review') }}</option>
        <option value="accepted"     @selected(request('status') === 'accepted')>{{ __('bids.accepted') }}</option>
        <option value="rejected"     @selected(request('status') === 'rejected')>{{ __('bids.rejected') }}</option>
    </select>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 px-4 h-12 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
        </svg>
        {{ __('common.filter') }}
    </button>
</form>

{{-- Bulk action toolbar (Phase 0 / task 0.8) — the form lives outside the
     bid list so we don't end up with nested <form> tags (the per-row accept
     and withdraw buttons each have their own form). Per-row checkboxes
     attach to this form via the `form=` attribute. --}}
@php $canBulkReject = auth()->user()?->hasPermission('bid.accept'); @endphp
@if($canBulkReject)
<form method="POST" action="{{ route('dashboard.bids.bulk-reject') }}" id="bids-bulk-form"
      onsubmit="return confirm('{{ __('bids.bulk_reject_confirm') }}');">
    @csrf
</form>
<div class="flex items-center justify-between gap-4 mb-3">
    <label class="inline-flex items-center gap-2 text-[13px] text-muted cursor-pointer">
        <input type="checkbox" id="bids-select-all"
               onchange="document.querySelectorAll('input[name=\'ids[]\'][form=\'bids-bulk-form\']').forEach(cb => cb.checked = this.checked);" />
        {{ __('common.select_all') }}
    </label>
    <button type="submit" form="bids-bulk-form"
            class="inline-flex items-center gap-2 px-4 h-10 rounded-[10px] text-[13px] font-semibold text-white bg-[#ff4d7f]/90 hover:bg-[#ff4d7f] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ __('bids.reject_selected') }}
    </button>
</div>
@endif

{{-- Bids list --}}
<div class="space-y-4">
    @forelse($bids as $bid)
    @php $pill = $statusPills[$bid['status']] ?? $statusPills['draft']; @endphp
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px] hover:border-accent/40 transition-colors">

        {{-- Top row: id + status + shortlisted star (left) / amount + diff (right) --}}
        <div class="flex items-start justify-between gap-4 mb-3 flex-wrap">
            <div class="flex items-center gap-3 flex-wrap">
                @if($canBulkReject && in_array($bid['status'], ['submitted', 'under_review'], true))
                    <input type="checkbox" name="ids[]" value="{{ $bid['numeric_id'] }}" form="bids-bulk-form"
                           class="form-checkbox rounded border-th-border bg-page"
                           onclick="event.stopPropagation();" />
                @endif
                <span class="text-[14px] text-muted">{{ $bid['id'] }}</span>
                <span class="inline-flex items-center gap-2 h-[26px] px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                    <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>
                    {{ __('status.' . $bid['status']) }}
                </span>
                @if($bid['shortlisted'])
                <span class="inline-flex items-center gap-1.5 h-[26px] px-3 rounded-full border border-[#ffb020]/20 bg-[#ffb020]/10 text-[#ffb020] text-[12px] font-medium">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    {{ __('bids.shortlisted') }}
                </span>
                @endif
            </div>
            <div class="text-end">
                <p class="text-[24px] font-semibold text-accent leading-[32px] tracking-[0.003em]">{{ $bid['amount'] }}</p>
                <div class="flex items-center justify-end gap-2 mt-1">
                    <span class="text-[12px] text-faint line-through">{{ $bid['old_amount'] }}</span>
                    <span class="text-[12px] font-semibold {{ !empty($bid['price_up']) ? 'text-[#ff4d7f]' : 'text-[#00d9b5]' }} inline-flex items-center gap-0.5">
                        @if(!empty($bid['price_up']))
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 5l8 10H4z"/></svg>
                        @else
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 19l-8-10h16z"/></svg>
                        @endif
                        {{ $bid['diff'] }}%
                    </span>
                </div>
            </div>
        </div>

        {{-- RFQ reference --}}
        <p class="text-[13px] text-muted mb-1">{{ $bid['rfq'] }} · {{ $bid['rfq_title'] }}</p>
        <div class="flex items-center gap-3 flex-wrap mb-4">
            <h3 class="text-[20px] font-semibold text-accent leading-[28px] tracking-[-0.022em]">{{ __('bids.supplier') }} {{ $bid['supplier'] }}</h3>
            @if(!empty($bid['verification_level']))
                <x-dashboard.verification-badge :level="$bid['verification_level']" />
            @endif
        </div>

        {{-- Meta row: rating · received · submitted · expires / delivery --}}
        <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
            <div class="flex items-center gap-5 text-[13px] text-muted flex-wrap">
                @if(!empty($bid['rating']))
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <span class="text-primary font-medium">{{ $bid['rating'] }}</span>
                </span>
                @endif
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                    {{ __('bids.received', ['count' => $bid['received']]) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    {{ __('bids.submitted_on', ['date' => $bid['submitted']]) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>
                    </svg>
                    {{ __('bids.expires_on', ['date' => $bid['expires']]) }}
                </span>
            </div>
            <div class="text-end text-[13px] text-muted leading-[18px]">
                {{ __('bids.delivery_days', ['days' => $bid['days']]) }}<br>
                {{ $bid['terms'] }}
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex items-center gap-3 flex-wrap">
            <a href="{{ route('dashboard.bids.show', ['id' => $bid['numeric_id']]) }}"
               class="flex-1 min-w-[180px] inline-flex items-center justify-center gap-2 px-5 h-11 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ __('common.view_details') }}
            </a>
            @if($bid['show_actions'])
            <a href="{{ route('dashboard.negotiations.show', ['id' => $bid['numeric_id']]) }}"
               class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[14px] font-medium text-white bg-[#8B5CF6] hover:bg-[#7c4dea] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                {{ __('bids.negotiate') }}
            </a>
            @can('bid.accept')
            <form method="POST" action="{{ route('dashboard.bids.accept', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('bids.accept') }}
                </button>
            </form>
            @endcan
            @can('bid.withdraw')
            <form method="POST" action="{{ route('dashboard.bids.withdraw', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[14px] font-medium text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/15 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('bids.reject') }}
                </button>
            </form>
            @endcan
            @endif
        </div>
    </div>
    @empty
    <x-dashboard.empty-state
        :title="__('bids.empty_title')"
        :message="__('bids.empty_message')"
        :cta="__('bids.view_rfqs')"
        :ctaUrl="route('dashboard.rfqs')" />
    @endforelse
</div>

@endsection
