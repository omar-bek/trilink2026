@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('rfq.title'))

@php
// Suffix the page subtitle with the authenticated user's company name so
// every tenant sees their own brand instead of a hardcoded one. Falls back
// to the bare subtitle when the user has no company (admin/government).
$companyName = auth()->user()?->company?->name;
$subtitle    = __('rfq.subtitle') . ($companyName ? ' · ' . $companyName : '');
@endphp

@section('content')

<x-dashboard.page-header :title="__('rfq.title')" :subtitle="$subtitle" />

{{-- =====================================================================
     Company-centric view switcher.
     The platform treats the COMPANY as the primary actor — a single
     company can publish RFQs (buyer-side) AND bid on other companies'
     RFQs (supplier-side). Managers/admins (and any company-attached
     user) can pivot between the two views from one place. The tabs are
     hidden when the controller didn't provide tab counts (legacy callers
     or users with no company), in which case the page renders exactly
     as it always did.
     ===================================================================== --}}
@if(!empty($tabCounts))
<div class="bg-surface border border-th-border rounded-2xl p-1.5 mb-6 inline-flex gap-1">
    @php
        $tabs = [
            ['key' => 'mine',        'label' => __('rfq.tab_mine'),        'count' => $tabCounts['mine']],
            ['key' => 'marketplace', 'label' => __('rfq.tab_marketplace'), 'count' => $tabCounts['marketplace']],
        ];
    @endphp
    @foreach($tabs as $tab)
    @php $isActive = ($activeTab ?? 'mine') === $tab['key']; @endphp
    <a href="{{ route('dashboard.rfqs', ['tab' => $tab['key']]) }}"
       class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-[13px] font-semibold transition-colors {{ $isActive ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.25)]' : 'text-muted hover:text-primary hover:bg-surface-2' }}">
        {{ $tab['label'] }}
        <span class="inline-flex items-center justify-center min-w-[22px] h-[20px] px-1.5 rounded-full text-[11px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-page text-muted' }}">{{ $tab['count'] }}</span>
    </a>
    @endforeach
</div>
@endif

{{-- Top stats — clickable; each card filters the list by that status. --}}
@php
    $rfqStatusCards = [
        ['key' => 'all',     'label' => __('rfq.all'),        'color' => 'blue',   'value' => $stats['all']],
        ['key' => 'open',    'label' => __('status.open'),    'color' => 'green',  'value' => $stats['open']],
        ['key' => 'expired', 'label' => __('status.expired'), 'color' => 'red',    'value' => $stats['expired']],
        ['key' => 'closed',  'label' => __('status.closed'),  'color' => 'orange', 'value' => $stats['closed']],
        ['key' => 'draft',   'label' => __('status.draft'),   'color' => 'slate',  'value' => $stats['draft']],
    ];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($rfqStatusCards as $card)
        <x-dashboard.stat-card
            :value="$card['value']"
            :label="$card['label']"
            :color="$card['color']"
            :href="route('dashboard.rfqs', array_filter(['status' => $card['key'], 'q' => $search ?: null, 'category' => $category ?: null, 'sort' => $sort !== 'newest' ? $sort : null]))"
            :active="$statusFilter === $card['key']" />
    @endforeach
</div>

{{-- Filters/sort --}}
<x-dashboard.filter-bar
    :action="route('dashboard.rfqs')"
    :search="$search"
    :placeholder="__('rfq.search_placeholder')"
    :clearUrl="route('dashboard.rfqs')"
    :hasFilters="$search !== '' || $category > 0 || $sort !== 'newest' || $statusFilter !== 'all'"
    :count="$resultCount"
    countLabel="rfq.found">
    <x-slot:hidden>
        <input type="hidden" name="status" value="{{ $statusFilter }}">
    </x-slot:hidden>
    <x-slot:filters>
        <select name="category"
                class="w-full lg:w-[200px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            <option value="0">{{ __('rfq.all_categories') }}</option>
            @foreach($categoryOptions as $opt)
                <option value="{{ $opt['id'] }}" @selected($category === $opt['id'])>{{ $opt['name'] }}</option>
            @endforeach
        </select>
        <select name="sort"
                class="w-full lg:w-[180px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            <option value="newest"    @selected($sort === 'newest')>{{ __('rfq.newest') }}</option>
            <option value="deadline"  @selected($sort === 'deadline')>{{ __('rfq.deadline') }}</option>
            <option value="value"     @selected($sort === 'value')>{{ __('rfq.value') }} ↓</option>
            <option value="most_bids" @selected($sort === 'most_bids')>{{ __('rfq.most_bids') }}</option>
        </select>
    </x-slot:filters>
</x-dashboard.filter-bar>

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('dashboard.purchase-requests.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
        {{ __('rfq.create') }}
    </a>
    <a href="{{ route('dashboard.rfqs') . '?' . http_build_query(array_merge(request()->query(), ['export' => 'csv'])) }}"
       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        {{ __('common.export_csv') }}
    </a>
</div>

{{-- RFQs list --}}
<div class="space-y-4">
    @forelse($rfqs as $rfq)
    @php $rfqUrl = route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]); @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-lg transition-all cursor-pointer"
         onclick="if(!event.target.closest('a')) window.location='{{ $rfqUrl }}'">
        <div class="flex items-start justify-between gap-4 mb-3">
            <span class="text-[12px] font-mono text-muted">#{{ $rfq['id'] }}</span>
            <x-dashboard.status-badge :status="$rfq['status']" />
        </div>

        <a href="{{ $rfqUrl }}">
            <h3 class="text-[18px] font-bold text-accent mb-3 hover:underline">{{ $rfq['title'] }}</h3>
        </a>

        <div class="flex items-center gap-2 mb-3 flex-wrap">
            @foreach($rfq['tags'] as $i => $tag)
            @php $color = $rfq['tag_colors'][$i] ?? 'slate'; @endphp
            <span class="text-[10px] font-medium px-2.5 py-1 rounded-full {{ $color === 'blue' ? 'text-accent bg-accent/10 border border-accent/20' : 'text-muted bg-surface-2 border border-th-border' }}">{{ $tag }}</span>
            @endforeach
        </div>

        <p class="text-[13px] text-muted mb-5 leading-relaxed">{{ $rfq['desc'] }}</p>

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-5 text-[12px] text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                    {{ __('rfq.items', ['count' => $rfq['items']]) }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-[#00d9b5] font-bold text-[14px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453.415 2.18.654A60.145 60.145 0 0118 30l-2.74 1.22m0 0l-5.94-2.28m5.94 2.28l-2.28-5.94"/></svg>
                    {{ $rfq['amount'] }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                    {{ $rfq['date'] }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    {{ __('rfq.bids', ['count' => $rfq['bids']]) }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard.bids', ['rfq' => $rfq['numeric_id']]) }}" class="text-[12px] font-semibold text-muted hover:text-accent transition-colors">{{ __('rfq.view_bids', ['count' => $rfq['bids']]) }}</a>
                <a href="{{ $rfqUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
                    {{ __('rfq.manage') }}
                    <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>
    @empty
    @if($search !== '' || $category > 0 || $statusFilter !== 'all')
        <x-dashboard.empty-state
            :title="__('rfq.no_results_title')"
            :message="__('rfq.no_results_message')"
            :cta="__('common.clear_filters')"
            :ctaUrl="route('dashboard.rfqs')" />
    @else
        <x-dashboard.empty-state
            :title="__('rfq.empty_title')"
            :message="__('rfq.empty_message')"
            :cta="__('rfq.create')"
            :ctaUrl="route('dashboard.purchase-requests.create')" />
    @endif
    @endforelse
</div>

@endsection
