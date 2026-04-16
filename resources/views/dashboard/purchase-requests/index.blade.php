@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.title'))

@php
$statusPills = [
    'approved'  => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'submitted' => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'open'      => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'pending'   => ['bg' => 'bg-[#ffb020]/10', 'border' => 'border-[#ffb020]/20', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]'],
    'draft'     => ['bg' => 'bg-muted/10',     'border' => 'border-muted/20',     'text' => 'text-muted',     'dot' => 'bg-muted'],
    'closed'    => ['bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/20', 'text' => 'text-[#ff4d7f]', 'dot' => 'bg-[#ff4d7f]'],
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] lg:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('pr.title') }}</h1>
        <p class="text-[14px] sm:text-[16px] text-muted mt-1">{{ __('pr.subtitle') }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <x-dashboard.export-csv-button :url="route('dashboard.purchase-requests') . '?' . http_build_query(array_merge(request()->query(), ['export' => 'csv']))" />
        @can('pr.create')
        <a href="{{ route('dashboard.purchase-requests.create') }}"
           class="inline-flex items-center gap-2 px-4 sm:px-5 h-11 sm:h-12 rounded-[12px] text-[13px] sm:text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('pr.new') }}
        </a>
        @endcan
    </div>
</div>

{{-- Clickable stat cards — each filters the list by that status --}}
@php
$statCards = [
    ['value' => $stats['total'],    'label' => __('pr.total'),            'color' => 'text-[#8b5cf6]', 'icon' => 'bg-[#8b5cf6]/10 text-[#8b5cf6]', 'filter' => null],
    ['value' => $stats['pending'],  'label' => __('pr.pending_approval'), 'color' => 'text-[#ffb020]', 'icon' => 'bg-[#ffb020]/10 text-[#ffb020]', 'filter' => 'pending_approval'],
    ['value' => $stats['approved'], 'label' => __('pr.approved'),         'color' => 'text-[#00d9b5]', 'icon' => 'bg-[#00d9b5]/10 text-[#00d9b5]', 'filter' => 'approved'],
    ['value' => $stats['progress'], 'label' => __('pr.in_progress'),      'color' => 'text-accent',    'icon' => 'bg-accent/10 text-accent',       'filter' => 'submitted'],
    ['value' => $stats['closed'],   'label' => __('pr.closed'),           'color' => 'text-[#ff4d7f]', 'icon' => 'bg-[#ff4d7f]/10 text-[#ff4d7f]', 'filter' => 'rejected'],
];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($statCards as $card)
    @php $isActive = ($statusFilter ?? null) === $card['filter'] || ($card['filter'] === null && $statusFilter === null); @endphp
    <a href="{{ route('dashboard.purchase-requests', $card['filter'] ? ['status' => $card['filter']] : []) }}"
       class="bg-surface border {{ $isActive ? 'border-accent/40 ring-1 ring-accent/20' : 'border-th-border' }} rounded-[16px] p-[17px] hover:border-accent/30 transition-all group">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-lg {{ $card['icon'] }} flex items-center justify-center flex-shrink-0">
                @if($card['filter'] === 'pending_approval')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @elseif($card['filter'] === 'approved')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif($card['filter'] === 'submitted')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                @elseif($card['filter'] === 'rejected')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25"/></svg>
                @endif
            </div>
            <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ $card['label'] }}</p>
        </div>
        <p class="text-[24px] font-bold {{ $card['color'] }} leading-tight tabular-nums">{{ $card['value'] }}</p>
    </a>
    @endforeach
</div>

{{-- Search + Status filter --}}
<form method="GET" action="{{ route('dashboard.purchase-requests') }}"
      class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('pr.search_placeholder') }}"
               class="w-full bg-page border border-th-border rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
    </div>
    <select name="status"
            class="w-full lg:w-[200px] bg-page border border-th-border rounded-[12px] px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 cursor-pointer">
        <option value="">{{ __('common.status') }}</option>
        <option value="draft"            @selected(request('status') === 'draft')>{{ __('status.draft') }}</option>
        <option value="pending_approval" @selected(request('status') === 'pending_approval')>{{ __('status.pending') }}</option>
        <option value="approved"         @selected(request('status') === 'approved')>{{ __('status.approved') }}</option>
        <option value="submitted"        @selected(request('status') === 'submitted')>{{ __('pr.in_progress') }}</option>
        <option value="rejected"         @selected(request('status') === 'rejected')>{{ __('status.rejected') }}</option>
    </select>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 px-4 h-12 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
        {{ __('pr.more_filters') }}
    </button>
</form>

{{-- Bulk approve toolbar --}}
@can('pr.approve')
<form method="POST" action="{{ route('dashboard.purchase-requests.bulk-approve') }}"
      x-data="{ count: 0 }"
      @change="count = $el.querySelectorAll('input[name=\'ids[]\']:checked').length">
    @csrf
    <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <p class="text-[13px] text-muted">
            <span x-text="count" class="font-semibold text-primary"></span> {{ __('pr.bulk_selected_suffix') }}
        </p>
        <button type="submit"
                x-bind:disabled="count === 0"
                :class="count === 0 ? 'opacity-40 cursor-not-allowed' : ''"
                class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00b894] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
            {{ __('pr.bulk_approve') }}
        </button>
    </div>
@endcan

{{-- PR List --}}
<div class="space-y-4">
    @forelse($requests as $r)
    @php
        $pill = $statusPills[$r['status']] ?? $statusPills['draft'];
        $rNumericId = $r['numeric_id'] ?? null;
    @endphp
    <div class="relative bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px] hover:border-accent/30 transition-all">
        {{-- Accent bar on start edge --}}
        @php
            $barColor = match($r['status']) {
                'approved', 'submitted', 'open' => 'from-[#00d9b5] to-[#14e5c3]',
                'pending' => 'from-[#ffb020] to-[#ffc94d]',
                'closed'  => 'from-[#ff4d7f] to-[#ff7da6]',
                default   => 'from-muted to-muted',
            };
        @endphp
        <div class="absolute top-0 bottom-0 start-0 w-[3px] rounded-s-[16px] bg-gradient-to-b {{ $barColor }}" aria-hidden="true"></div>

        <div class="flex items-start gap-4">
            @can('pr.approve')
                @if($r['status'] === 'pending' && $rNumericId)
                    <label class="flex items-center pt-1 flex-shrink-0">
                        <input type="checkbox" name="ids[]" value="{{ $rNumericId }}"
                               class="w-5 h-5 rounded border-th-border bg-page text-[#00d9b5] focus:ring-[#00d9b5]/30">
                    </label>
                @else
                    <div class="w-5 h-5 flex-shrink-0 hidden sm:block"></div>
                @endif
            @endcan

            <a href="{{ route('dashboard.purchase-requests.show', ['id' => $r['numeric_id']]) }}" class="block flex-1 min-w-0">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        {{-- ID + Status + Category --}}
                        <div class="flex items-center gap-2.5 mb-2 flex-wrap">
                            <span class="text-[12px] font-mono text-muted px-2 h-[22px] inline-flex items-center rounded-md bg-page border border-th-border">{{ $r['id'] }}</span>
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[11px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>
                                {{ __('status.' . $r['status']) }}
                            </span>
                            <span class="inline-flex items-center h-[22px] px-2 rounded-md bg-accent/10 border border-accent/20 text-accent text-[11px] font-medium">{{ $r['tag'] }}</span>
                        </div>

                        {{-- Title --}}
                        <h3 class="text-[18px] sm:text-[20px] font-bold text-accent leading-[26px] tracking-[-0.015em] mb-1 group-hover:underline">{{ $r['title'] }}</h3>

                        @if(!empty($r['desc']))
                        <p class="text-[13px] text-muted leading-relaxed mb-3 line-clamp-2">{{ $r['desc'] }}</p>
                        @endif

                        {{-- Meta: creator + date --}}
                        <div class="flex items-center gap-5 text-[13px] text-muted flex-wrap">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                {{ $r['creator'] }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                {{ $r['date'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Right side: amount + RFQ/bid counts --}}
                    <div class="text-end flex-shrink-0">
                        <p class="text-[22px] sm:text-[24px] font-bold text-accent leading-tight tabular-nums">{{ $r['amount'] }}</p>
                        <div class="flex items-center justify-end gap-4 mt-2 text-[12px] text-muted">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                {{ $r['rfqs'] }} {{ __('nav.rfqs') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ $r['bids'] }} {{ __('nav.bids') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Lifecycle progress --}}
                @if(!empty($r['progress']))
                <div class="mt-4 pt-4 border-t border-th-border">
                    <div class="flex items-center justify-between text-[11px] text-muted mb-2">
                        <span class="font-semibold uppercase tracking-wider">{{ __('pr.rfq_creation_progress') }}</span>
                        <span class="font-bold text-primary tabular-nums">{{ $r['progress']['done'] }} / {{ $r['progress']['total'] }}</span>
                    </div>
                    <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-accent to-[#00d9b5] rounded-full transition-all duration-500" style="width: {{ ($r['progress']['done'] / max($r['progress']['total'], 1)) * 100 }}%"></div>
                    </div>
                </div>
                @endif
            </a>
        </div>
    </div>
    @empty
    <x-dashboard.empty-state
        :title="__('pr.empty_title')"
        :message="__('pr.empty_message')"
        :cta="auth()->user()?->hasPermission('pr.create') ? __('pr.new') : null"
        :ctaUrl="auth()->user()?->hasPermission('pr.create') ? route('dashboard.purchase-requests.create') : null" />
    @endforelse
</div>

@can('pr.approve')
</form>
@endcan

@endsection
