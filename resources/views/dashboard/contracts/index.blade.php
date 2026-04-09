@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.title'))

@section('content')

{{-- ============================================================
     CONTRACTS INDEX — Unified buyer + supplier listing
     Design language: Trilink dashboard tokens (bg-page / bg-surface
     / accent #4f7cff / teal #00d9b5 / orange #ffb020 / red #ff4d7f
     / purple #8b5cf6). Works in both light + dark mode because it
     uses theme tokens instead of raw hex where possible.
     ============================================================ --}}

{{-- Page header: title on the left, primary CTA on the right. --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <x-dashboard.page-header :title="__('contracts.title')" :subtitle="__('contracts.subtitle')" :back="route('dashboard')" />
    <a href="{{ route('dashboard.contracts.analytics') }}"
       class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-gradient-to-r from-[#8b5cf6] to-[#4f7cff] hover:opacity-95 shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-page">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        {{ __('contracts.analytics_title') }}
    </a>
</div>

{{-- ============================================================
     KPI STRIP — Total · Active · Completed · Total Value
     Clickable: tapping a card (except Total Value) filters the
     list to that status. The currently-active filter gets an
     accent ring via the `:active` prop.
     ============================================================ --}}
@php
    $contractStatusCards = [
        ['key' => 'all',       'label' => __('contracts.total'),     'color' => 'slate',  'value' => $stats['total']],
        ['key' => 'active',    'label' => __('contracts.active'),    'color' => 'orange', 'value' => $stats['active']],
        ['key' => 'completed', 'label' => __('contracts.completed'), 'color' => 'green',  'value' => $stats['completed']],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @foreach($contractStatusCards as $card)
        <x-dashboard.stat-card
            :value="$card['value']"
            :label="$card['label']"
            :color="$card['color']"
            :href="route('dashboard.contracts', array_filter([
                'status'    => $card['key'] === 'all' ? null : $card['key'],
                'direction' => $direction !== 'all' ? $direction : null,
                'q'         => request('q') ?: null,
                'sort'      => $sort !== 'newest' ? $sort : null,
            ]))"
            :active="$statusFilter === $card['key']" />
    @endforeach
    <x-dashboard.stat-card :value="$stats['value']" :label="__('contracts.total_value')" color="purple" />
</div>

{{-- ============================================================
     FILTER CARD — Direction segmented control + status chip row
     Merged into a single card so the filter state is visible at
     a glance. The segmented control uses the same accent color
     as the detail page direction pill so buyers/sellers feel at
     home on either view.
     ============================================================ --}}
@php
    $directionChips = [
        ['key' => 'all',     'label' => __('contracts.direction_all'),     'icon' => 'M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5'],
        ['key' => 'buying',  'label' => __('contracts.direction_buying'),  'icon' => 'M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3'],
        ['key' => 'selling', 'label' => __('contracts.direction_selling'), 'icon' => 'M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18'],
    ];

    // Status pill row — wraps the old <select> into discoverable
    // chips so the user can see every choice without opening a
    // dropdown. "all" is always first.
    $statusChips = [
        ['key' => 'all',       'label' => __('contracts.all_status')],
        ['key' => 'active',    'label' => __('contracts.active')],
        ['key' => 'completed', 'label' => __('contracts.completed')],
        ['key' => 'draft',     'label' => __('status.draft')],
        ['key' => 'pending',   'label' => __('status.pending')],
        ['key' => 'cancelled', 'label' => __('status.cancelled')],
    ];
@endphp
<div class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-5">
    {{-- Row 1: direction segmented tabs --}}
    <div class="flex items-center gap-1.5 p-1 rounded-[12px] bg-page border border-th-border w-full sm:w-auto sm:inline-flex mb-3">
        @foreach($directionChips as $chip)
            @php
                $isActive = $direction === $chip['key'];
                $count    = $directionCounts[$chip['key']] ?? 0;
            @endphp
            <a href="{{ route('dashboard.contracts', array_filter([
                    'direction' => $chip['key'] === 'all' ? null : $chip['key'],
                    'status'    => $statusFilter !== 'all' ? $statusFilter : null,
                    'q'         => request('q') ?: null,
                    'sort'      => $sort !== 'newest' ? $sort : null,
                ])) }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-4 h-9 rounded-[10px] text-[12px] font-semibold transition-all
                      {{ $isActive
                          ? 'bg-accent text-white shadow-[0_6px_20px_-8px_rgba(79,124,255,0.6)]'
                          : 'text-body hover:text-primary hover:bg-surface' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chip['icon'] }}"/></svg>
                <span>{{ $chip['label'] }}</span>
                <span class="inline-flex items-center justify-center min-w-[20px] h-[18px] px-1.5 rounded-full text-[10px] font-bold
                             {{ $isActive ? 'bg-white/25 text-white' : 'bg-elevated text-muted' }}">
                    {{ $count }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Row 2: status filter chips --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[11px] font-medium text-faint uppercase tracking-wider me-1">{{ __('contracts.all_status') }}:</span>
        @foreach($statusChips as $chip)
            @php $isActive = $statusFilter === $chip['key']; @endphp
            <a href="{{ route('dashboard.contracts', array_filter([
                    'status'    => $chip['key'] === 'all' ? null : $chip['key'],
                    'direction' => $direction !== 'all' ? $direction : null,
                    'q'         => request('q') ?: null,
                    'sort'      => $sort !== 'newest' ? $sort : null,
                ])) }}"
               class="inline-flex items-center h-7 px-3 rounded-full text-[11px] font-semibold border transition-colors
                      {{ $isActive
                          ? 'bg-accent/10 text-accent border-accent/40'
                          : 'bg-page text-muted border-th-border hover:text-primary hover:border-accent/30' }}">
                {{ $chip['label'] }}
            </a>
        @endforeach
    </div>
</div>

{{-- ============================================================
     LIST CARD — Toolbar (search + sort + export) then rows.
     Everything lives in one surface card so the rows sit inside
     a visual container with the toolbar.
     ============================================================ --}}
<div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
    {{-- Toolbar: title + result count on the left, search/sort/export on the right --}}
    <form method="GET" action="{{ route('dashboard.contracts') }}" class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-[16px] sm:text-[18px] font-bold text-primary leading-tight">{{ __('contracts.all') }}</h3>
            @if(isset($paginator) && $paginator->total() > 0)
                <p class="text-[11px] text-muted mt-0.5">
                    {{ __('common.showing_results', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
                </p>
            @endif
        </div>

        {{-- Preserve direction + status across search submits --}}
        @if($direction !== 'all')
            <input type="hidden" name="direction" value="{{ $direction }}">
        @endif
        @if($statusFilter !== 'all')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('contracts.search_placeholder') }}"
                       class="bg-page border border-th-border rounded-[10px] ps-9 pe-3 h-10 text-[12px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40 focus:ring-2 focus:ring-accent/15 w-[220px] sm:w-[260px] transition-all">
            </div>

            {{-- Sort --}}
            <select name="sort" onchange="this.form.submit()"
                    class="bg-page border border-th-border rounded-[10px] px-3 h-10 text-[12px] text-primary focus:outline-none focus:border-accent/40 focus:ring-2 focus:ring-accent/15 transition-all">
                <option value="newest"      @selected($sort === 'newest')>{{ __('contracts.sort_newest') }}</option>
                <option value="oldest"      @selected($sort === 'oldest')>{{ __('contracts.sort_oldest') }}</option>
                <option value="value_desc"  @selected($sort === 'value_desc')>{{ __('contracts.sort_value_desc') }}</option>
                <option value="value_asc"   @selected($sort === 'value_asc')>{{ __('contracts.sort_value_asc') }}</option>
                <option value="ending_soon" @selected($sort === 'ending_soon')>{{ __('contracts.sort_ending_soon') }}</option>
            </select>

            {{-- Export CSV --}}
            <a href="{{ route('dashboard.contracts.export-csv', array_filter([
                    'status'    => $statusFilter !== 'all' ? $statusFilter : null,
                    'direction' => $direction !== 'all' ? $direction : null,
                    'q'         => request('q') ?: null,
                    'sort'      => $sort !== 'newest' ? $sort : null,
                ])) }}"
               class="inline-flex items-center gap-1.5 bg-page border border-th-border rounded-[10px] px-3 h-10 text-[12px] font-semibold text-primary hover:border-accent/40 hover:text-accent transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span class="hidden sm:inline">{{ __('contracts.export_csv') }}</span>
            </a>
        </div>
    </form>

    {{-- Bulk select bar + rows live inside one Alpine root so the
         sticky action bar at the bottom-center can reflect the
         current selection without an extra store. --}}
    <div x-data="contractBulkSelect()" class="relative">
        {{-- Select-all header bar --}}
        @if(count($contracts) > 0)
        <div class="flex items-center justify-between gap-3 px-4 h-10 rounded-[10px] bg-page border border-th-border mb-3">
            <label class="inline-flex items-center gap-2 text-[12px] font-medium text-muted cursor-pointer select-none">
                <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="selected.length === total && total > 0"
                       class="w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40">
                {{ __('contracts.select_all') }}
            </label>
            <div class="text-[11px] text-faint">
                <span x-show="selected.length > 0" x-cloak><span x-text="selected.length"></span> {{ __('contracts.selected') }}</span>
            </div>
        </div>
        @endif

        {{-- ==== ROWS ==== --}}
        <div class="space-y-3">
            @forelse($contracts as $c)
            @php
                $isBuying = ($c['direction'] ?? null) === 'buying';

                // Direction-aware palette: buyers get accent-blue,
                // suppliers get the teal. The decorative accent bar
                // on the left edge of each row uses these.
                $dirBar     = $isBuying ? 'from-[#4f7cff] to-[#6b91ff]' : 'from-[#00d9b5] to-[#14e5c3]';
                $dirBubble  = $isBuying ? 'bg-accent/10 text-accent border-accent/30' : 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30';
                $dirLabel   = $isBuying ? __('contracts.direction_buying') : __('contracts.direction_selling');
                $dirIcon    = $isBuying ? 'M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3' : 'M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18';
                $cpLabel    = $isBuying ? __('contracts.supplier') : __('contracts.buyer');

                // Days-left pill — warns about contracts ending soon.
                // Green if plenty of runway, orange if <= 14 days, red if <= 3 days.
                $daysLeft = $c['days_left'] ?? null;
                if ($daysLeft === null) {
                    $daysLeftTone = 'bg-elevated text-muted border-th-border';
                } elseif ($daysLeft <= 3) {
                    $daysLeftTone = 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30';
                } elseif ($daysLeft <= 14) {
                    $daysLeftTone = 'bg-[#ffb020]/10 text-[#ffb020] border-[#ffb020]/30';
                } else {
                    $daysLeftTone = 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30';
                }
            @endphp
            <div class="group relative overflow-hidden rounded-[12px] bg-page border border-th-border hover:border-accent/30 hover:shadow-[0_14px_40px_-20px_rgba(79,124,255,0.35)] transition-all">
                {{-- Decorative accent bar (direction-coloured) --}}
                <div class="absolute top-0 bottom-0 start-0 w-[3px] bg-gradient-to-b {{ $dirBar }}"></div>

                <div class="flex items-start gap-3 p-4 sm:p-5 ps-5 sm:ps-6">
                    {{-- Checkbox — stays out of the clickable area so it
                         doesn't steal the row link. --}}
                    <label class="flex items-center justify-center w-5 h-5 mt-1 flex-shrink-0 cursor-pointer">
                        <input type="checkbox" value="{{ $c['numeric_id'] }}" @change="toggle({{ $c['numeric_id'] }}, $event.target.checked)"
                               class="w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40"
                               aria-label="{{ __('contracts.select_row') }}">
                    </label>

                    {{-- Direction icon bubble --}}
                    <div class="hidden sm:flex items-center justify-center w-10 h-10 rounded-[10px] border {{ $dirBubble }} flex-shrink-0">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $dirIcon }}"/></svg>
                    </div>

                    {{-- Main content --}}
                    <a href="{{ route('dashboard.contracts.show', ['id' => $c['numeric_id']]) }}" class="block flex-1 min-w-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 rounded-[8px]">
                        {{-- Line 1: id + status + direction pill · amount --}}
                        <div class="flex items-start justify-between gap-4 flex-wrap mb-1.5">
                            <div class="flex items-center gap-2 flex-wrap min-w-0">
                                <span class="text-[11px] font-mono text-muted">{{ $c['id'] }}</span>
                                <x-dashboard.status-badge :status="$c['status']" />
                                <span class="inline-flex items-center gap-1 px-2 h-[20px] rounded-full border text-[10px] font-bold uppercase tracking-wider {{ $dirBubble }}">
                                    {{ $dirLabel }}
                                </span>
                            </div>
                            <div class="text-end">
                                <p class="text-[20px] sm:text-[22px] font-bold text-[#00d9b5] leading-none">{{ $c['amount'] }}</p>
                            </div>
                        </div>

                        {{-- Line 2: title --}}
                        <h3 class="text-[15px] sm:text-[16px] font-bold text-primary group-hover:text-accent transition-colors mb-1 truncate">
                            {{ $c['title'] }}
                        </h3>

                        {{-- Line 3: counterparty --}}
                        <p class="inline-flex items-center gap-1.5 text-[12px] text-muted mb-4">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            {{ $cpLabel }}:
                            <span class="text-body font-semibold truncate">{{ $c['counterparty'] ?? $c['supplier'] }}</span>
                        </p>

                        {{-- Meta grid: started · expected · days-left pill · paid/received --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-[11px] mb-3 pt-3 border-t border-th-border">
                            <div>
                                <p class="text-faint uppercase tracking-wider text-[10px] mb-0.5">{{ __('common.started') }}</p>
                                <p class="text-body font-semibold">{{ $c['started'] }}</p>
                            </div>
                            <div>
                                <p class="text-faint uppercase tracking-wider text-[10px] mb-0.5">{{ __('common.expected') }}</p>
                                <p class="text-body font-semibold">{{ $c['expected'] ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-faint uppercase tracking-wider text-[10px] mb-0.5">{{ __('contracts.days_left') }}</p>
                                @if($daysLeft !== null)
                                    <span class="inline-flex items-center gap-1 px-2 h-[20px] rounded-full border text-[10px] font-bold {{ $daysLeftTone }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $daysLeft }} {{ __('common.days') }}
                                    </span>
                                @else
                                    <p class="text-faint font-semibold">—</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-faint uppercase tracking-wider text-[10px] mb-0.5">{{ $isBuying ? __('contracts.paid') : __('contracts.received') }}</p>
                                <p class="text-[#00d9b5] font-bold">{{ $c['received'] ?? '—' }}</p>
                            </div>
                        </div>

                        {{-- Progress bar --}}
                        <div>
                            <div class="flex items-center justify-between text-[11px] mb-1.5">
                                <span class="text-muted font-medium">{{ $c['progress_label'] }}</span>
                                <span class="text-primary font-bold tabular-nums">{{ $c['progress'] }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     style="width: {{ $c['progress'] }}%; background: {{ $c['progress_color'] }};"
                                     role="progressbar"
                                     aria-valuenow="{{ $c['progress'] }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-label="{{ $c['progress_label'] }}"></div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            @empty
                @if(request('q') || $statusFilter !== 'all' || $direction !== 'all')
                    <x-dashboard.empty-state
                        :title="__('contracts.no_results_title')"
                        :message="__('contracts.no_results_message')"
                        :cta="__('common.clear_filters')"
                        :ctaUrl="route('dashboard.contracts')" />
                @else
                    <x-dashboard.empty-state
                        :title="__('contracts.empty_title')"
                        :message="__('contracts.empty_message')"
                        :cta="__('pr.new')"
                        :ctaUrl="route('dashboard.purchase-requests.create')" />
                @endif
            @endforelse
        </div>

        @if(isset($paginator) && $paginator->hasPages())
        <div class="mt-6 pt-5 border-t border-th-border">
            {{ $paginator->onEachSide(1)->links() }}
        </div>
        @endif

        {{-- ==== STICKY BULK ACTION BAR ====
             Floats at the bottom-center of the viewport when any
             row is selected. Mirrors the inline toolbar but stays
             visible as the user scrolls through a long list. --}}
        <div x-show="selected.length > 0" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-40">
            <div class="flex items-center gap-3 ps-4 pe-2 h-12 rounded-full bg-surface border border-th-border shadow-[0_20px_60px_-12px_rgba(0,0,0,0.35)] backdrop-blur-xl">
                <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-primary">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-accent text-white text-[11px] font-bold" x-text="selected.length"></span>
                    {{ __('contracts.selected') }}
                </span>
                <div class="w-px h-6 bg-th-border"></div>
                <a :href="bulkExportUrl()"
                   class="inline-flex items-center gap-1.5 h-9 px-3 rounded-full text-[12px] font-bold text-white bg-accent hover:bg-accent-h transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    {{ __('contracts.bulk_export_csv') }}
                </a>
                <button type="button" @click="clear()" class="inline-flex items-center justify-center w-9 h-9 rounded-full text-muted hover:text-primary hover:bg-elevated transition-colors" aria-label="{{ __('common.clear') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>{{-- /contractBulkSelect --}}
</div>

@push('scripts')
<script>
    // Alpine.data factory for the bulk-select panel above. Lives in
    // a stack so it loads once per page even if other tables on the
    // dashboard ever reuse the same component name.
    document.addEventListener('alpine:init', () => {
        if (window.__contractBulkSelectInit) return;
        window.__contractBulkSelectInit = true;
        Alpine.data('contractBulkSelect', () => ({
            selected: [],
            get total() {
                return this.$root.querySelectorAll('input[type=checkbox][value]').length;
            },
            toggle(id, checked) {
                if (checked) {
                    if (!this.selected.includes(id)) this.selected.push(id);
                } else {
                    this.selected = this.selected.filter(x => x !== id);
                }
            },
            toggleAll(checked) {
                this.selected = [];
                if (checked) {
                    this.$root.querySelectorAll('input[type=checkbox][value]').forEach(cb => {
                        cb.checked = true;
                        const v = parseInt(cb.value, 10);
                        if (!isNaN(v)) this.selected.push(v);
                    });
                } else {
                    this.$root.querySelectorAll('input[type=checkbox][value]').forEach(cb => { cb.checked = false; });
                }
            },
            clear() {
                this.selected = [];
                this.$root.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; });
            },
            bulkExportUrl() {
                const base = '{{ route('dashboard.contracts.export-csv') }}';
                const params = this.selected.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
                return base + '?' + params;
            },
        }));
    });
</script>
@endpush

@endsection
