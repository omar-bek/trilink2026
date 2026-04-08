@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.title'))

@php
/**
 * Visual spec follows the Figma "Purchase Requests" frame (node 124:2841) exactly:
 *   - Stat cards: bg #1a1d29, padding 17, rounded 16, number 24px in color, label 14px #b4b6c0
 *   - PR card:    bg #1a1d29, padding 25, rounded 16
 *   - Status pill: bg color@10%, border color@20%, 26h, dot 6px, text 12 medium
 *   - Tag pill:   bg #252932, 24h, text 12 #b4b6c0
 *   - Title:      20px semibold #4f7cff
 *   - Amount:     24px semibold #4f7cff (right-aligned)
 *   - Description 14px #b4b6c0; meta row 14px #b4b6c0
 *   - Progress: track #252932, fill #00d9b5
 */

// Stat card colors — exact hex from Figma.
$statColors = [
    'purple' => 'text-[#8b5cf6]',
    'orange' => 'text-[#ffb020]',
    'green'  => 'text-[#00d9b5]',
    'blue'   => 'text-[#4f7cff]',
    'muted'  => 'text-[#b4b6c0]',
];

// Status pill colors — bg is the color at 10% opacity, border at 20%, text the solid color.
$statusPills = [
    'approved'  => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]',  'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'submitted' => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]',  'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'open'      => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]',  'text' => 'text-[#00d9b5]', 'dot' => 'bg-[#00d9b5]'],
    'pending'   => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]'],
    'draft'     => ['bg' => 'bg-[rgba(180,182,192,0.1)]','border' => 'border-[rgba(180,182,192,0.2)]','text' => 'text-[#b4b6c0]', 'dot' => 'bg-[#b4b6c0]'],
    'closed'    => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'dot' => 'bg-[#ffb020]'],
];

// "Created by" leading icon color — matches the row's status to give the meta line
// a quick visual cue without repeating the badge.
$creatorIconColor = [
    'approved'  => 'text-[#00d9b5]',
    'submitted' => 'text-[#00d9b5]',
    'pending'   => 'text-[#ffb020]',
    'closed'    => 'text-[#ffb020]',
    'draft'     => 'text-[#b4b6c0]',
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] lg:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ __('pr.title') }}</h1>
        <p class="text-[14px] sm:text-[15px] lg:text-[16px] text-[#b4b6c0] mt-1">{{ __('pr.subtitle') }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <x-dashboard.export-csv-button :url="route('dashboard.purchase-requests') . '?' . http_build_query(array_merge(request()->query(), ['export' => 'csv']))" />
        @can('pr.create')
        <a href="{{ route('dashboard.purchase-requests.create') }}"
           class="group inline-flex items-center gap-2 px-4 sm:px-5 h-11 sm:h-12 rounded-[12px] text-[13px] sm:text-[14px] font-semibold text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4f7cff]/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0f1117]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('pr.new') }}
        </a>
        @endcan
    </div>
</div>

{{-- Search bar --}}
<form method="GET" action="{{ route('dashboard.purchase-requests') }}"
      class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-[#b4b6c0] absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('pr.search_placeholder') }}"
               class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-white placeholder:text-[rgba(255,255,255,0.5)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors" />
    </div>
    <div class="flex-shrink-0 w-full lg:w-[200px]">
        <select name="status"
                class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] px-4 h-12 text-[14px] text-white focus:outline-none focus:border-[#4f7cff]/50 cursor-pointer">
            <option value="">{{ __('common.status') }}</option>
            <option value="draft"     @selected(request('status') === 'draft')>{{ __('status.draft') }}</option>
            <option value="pending"   @selected(request('status') === 'pending')>{{ __('status.pending') }}</option>
            <option value="approved"  @selected(request('status') === 'approved')>{{ __('status.approved') }}</option>
        </select>
    </div>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 px-4 h-12 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
        {{ __('pr.more_filters') }}
    </button>
</form>

{{-- Stats grid --}}
@php
$statCards = [
    ['value' => $stats['total'],    'label' => __('pr.total'),            'color' => 'purple'],
    ['value' => $stats['pending'],  'label' => __('pr.pending_approval'), 'color' => 'orange'],
    ['value' => $stats['approved'], 'label' => __('pr.approved'),         'color' => 'green'],
    ['value' => $stats['progress'], 'label' => __('pr.in_progress'),      'color' => 'blue'],
    ['value' => $stats['closed'],   'label' => __('pr.closed'),           'color' => 'muted'],
];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($statCards as $card)
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-4 sm:p-[17px] transition-transform hover:-translate-y-0.5">
        <p class="text-[22px] sm:text-[24px] font-semibold {{ $statColors[$card['color']] }} leading-tight tracking-[0.003em]">{{ $card['value'] }}</p>
        <p class="text-[12px] sm:text-[14px] text-[#b4b6c0] leading-[20px] mt-1">{{ $card['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- List (wrapped in a form so the manager can bulk-approve pending rows) --}}
@can('pr.approve')
<form method="POST" action="{{ route('dashboard.purchase-requests.bulk-approve') }}"
      x-data="{ count: 0 }"
      @change="count = $el.querySelectorAll('input[name=\'ids[]\']:checked').length">
    @csrf
    <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <p class="text-[13px] text-[#b4b6c0]">
            <span x-text="count"></span> {{ __('pr.bulk_selected_suffix') }}
        </p>
        <button type="submit"
                x-bind:disabled="count === 0"
                x-bind:class="count === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
            {{ __('pr.bulk_approve') }}
        </button>
    </div>
@endcan

{{-- List --}}
<div class="space-y-4">
    @forelse($requests as $r)
    @php
        $pill = $statusPills[$r['status']] ?? $statusPills['draft'];
        $iconColor = $creatorIconColor[$r['status']] ?? 'text-[#b4b6c0]';
        $rNumericId = $r['numeric_id'] ?? null;
    @endphp
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px] hover:border-[#4f7cff]/40 transition-colors">
        <div class="flex items-start gap-4">
            @can('pr.approve')
                @if($r['status'] === 'pending' && $rNumericId)
                    <label class="flex items-center pt-1 flex-shrink-0">
                        <input type="checkbox" name="ids[]" value="{{ $rNumericId }}"
                               class="w-5 h-5 rounded border-[rgba(255,255,255,0.2)] bg-[#0f1117] text-[#00d9b5] focus:ring-[#00d9b5]">
                    </label>
                @else
                    <div class="w-5 h-5 flex-shrink-0"></div>
                @endif
            @endcan
            <a href="{{ route('dashboard.purchase-requests.show', ['id' => $r['numeric_id']]) }}" class="block flex-1 min-w-0">

        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2 flex-wrap">
                    <span class="text-[14px] text-[#b4b6c0]">{{ $r['id'] }}</span>
                    <span class="inline-flex items-center gap-2 h-[26px] px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                        <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>
                        {{ __('status.' . $r['status']) }}
                    </span>
                    <span class="inline-flex items-center h-6 px-2 rounded-full bg-[#252932] text-[#b4b6c0] text-[12px]">{{ $r['tag'] }}</span>
                </div>
                <h3 class="text-[20px] font-semibold text-[#4f7cff] leading-[28px] tracking-[-0.022em] mb-1">{{ $r['title'] }}</h3>
                @if(!empty($r['desc']))
                <p class="text-[14px] text-[#b4b6c0] leading-[20px] mb-3">{{ $r['desc'] }}</p>
                @endif

                <div class="flex items-center gap-6 text-[14px] text-[#b4b6c0]">
                    <span class="inline-flex items-center gap-2">
                        @if(in_array($r['status'], ['approved', 'submitted'], true))
                            <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                        @elseif($r['status'] === 'pending')
                            <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        @elseif($r['status'] === 'closed')
                            <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6m-6 0l6-6"/></svg>
                        @else
                            <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                        @endif
                        {{ __('pr.created_by') }} {{ $r['creator'] }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $r['date'] }}
                    </span>
                </div>
            </div>

            <div class="text-end flex-shrink-0">
                <p class="text-[24px] font-semibold text-[#4f7cff] leading-[32px] tracking-[0.003em]">{{ $r['amount'] }}</p>
                <div class="flex items-center justify-end gap-4 mt-2 text-[12px] sm:text-[14px] text-[#b4b6c0]">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ $r['rfqs'] }} {{ __('nav.rfqs') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ $r['bids'] }} {{ __('nav.bids') }}
                    </span>
                </div>
            </div>
        </div>

        @if(!empty($r['progress']))
        <div class="mt-5">
            <div class="flex items-center justify-between text-[12px] text-[#b4b6c0] mb-2">
                <span>{{ __('pr.rfq_creation_progress') }}</span>
                <span class="font-medium">{{ $r['progress']['done'] }} / {{ $r['progress']['total'] }}</span>
            </div>
            <div class="w-full h-2 bg-[#252932] rounded-full overflow-hidden">
                <div class="h-full bg-[#00d9b5] rounded-full transition-all" style="width: {{ ($r['progress']['done'] / max($r['progress']['total'], 1)) * 100 }}%"></div>
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
