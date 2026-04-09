@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.title'))

@section('content')

<x-dashboard.page-header :title="__('contracts.title')" :subtitle="__('contracts.subtitle')" :back="route('dashboard')" />

{{-- Stats — clickable; clicking a card filters the list to that status. --}}
@php
    $contractStatusCards = [
        ['key' => 'all',       'label' => __('contracts.total'),     'color' => 'slate',  'value' => $stats['total']],
        ['key' => 'active',    'label' => __('contracts.active'),    'color' => 'orange', 'value' => $stats['active']],
        ['key' => 'completed', 'label' => __('contracts.completed'), 'color' => 'green',  'value' => $stats['completed']],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-8">
    @foreach($contractStatusCards as $card)
        <x-dashboard.stat-card
            :value="$card['value']"
            :label="$card['label']"
            :color="$card['color']"
            :href="route('dashboard.contracts', array_filter(['status' => $card['key'] === 'all' ? null : $card['key'], 'q' => request('q') ?: null, 'sort' => request('sort') !== 'newest' ? request('sort') : null]))"
            :active="$statusFilter === $card['key']" />
    @endforeach
    <x-dashboard.stat-card :value="$stats['value']" :label="__('contracts.total_value')" color="purple" />
</div>

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <form method="GET" action="{{ route('dashboard.contracts') }}" class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h3 class="text-[18px] font-bold text-primary">{{ __('contracts.all') }}</h3>

        <div class="flex items-center gap-2 flex-wrap">
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('contracts.search_placeholder') }}"
                       class="bg-page border border-th-border rounded-xl ps-9 pe-3 py-2 text-[12px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40 w-[220px]">
            </div>

            <select name="status" onchange="this.form.submit()"
                    class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/40">
                <option value="all"       @selected($statusFilter === 'all')>{{ __('contracts.all_status') }}</option>
                <option value="active"    @selected($statusFilter === 'active')>{{ __('contracts.active') }}</option>
                <option value="completed" @selected($statusFilter === 'completed')>{{ __('contracts.completed') }}</option>
                <option value="draft"     @selected($statusFilter === 'draft')>{{ __('status.draft') }}</option>
                <option value="pending"   @selected($statusFilter === 'pending')>{{ __('status.pending') }}</option>
                <option value="cancelled" @selected($statusFilter === 'cancelled')>{{ __('status.cancelled') }}</option>
            </select>

            <select name="sort" onchange="this.form.submit()"
                    class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/40">
                <option value="newest"      @selected($sort === 'newest')>{{ __('contracts.sort_newest') }}</option>
                <option value="oldest"      @selected($sort === 'oldest')>{{ __('contracts.sort_oldest') }}</option>
                <option value="value_desc"  @selected($sort === 'value_desc')>{{ __('contracts.sort_value_desc') }}</option>
                <option value="value_asc"   @selected($sort === 'value_asc')>{{ __('contracts.sort_value_asc') }}</option>
                <option value="ending_soon" @selected($sort === 'ending_soon')>{{ __('contracts.sort_ending_soon') }}</option>
            </select>

            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-accent text-white rounded-xl px-3 py-2 text-[12px] font-semibold hover:bg-accent-h">
                {{ __('common.search') }}
            </button>

            <a href="{{ route('dashboard.contracts.export-csv', array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'q' => request('q') ?: null, 'sort' => $sort !== 'newest' ? $sort : null])) }}"
               class="inline-flex items-center gap-1.5 bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] font-semibold text-primary hover:border-accent/40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                {{ __('contracts.export_csv') }}
            </a>
        </div>
    </form>

    @if(isset($paginator) && $paginator->total() > 0)
    <p class="text-[12px] text-muted mb-3">
        {{ __('common.showing_results', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
    </p>
    @endif

    <div class="space-y-4">
        @forelse($contracts as $c)
        <a href="{{ route('dashboard.contracts.show', ['id' => $c['numeric_id']]) }}" class="block bg-page border border-th-border rounded-xl p-5 hover:border-accent/30 hover:shadow-lg transition-all">
            <div class="flex items-start justify-between gap-4 mb-2 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">{{ $c['id'] }}</span>
                    <x-dashboard.status-badge :status="$c['status']" />
                </div>
                <div class="text-end">
                    <p class="text-[20px] font-bold text-[#00d9b5]">{{ $c['amount'] }}</p>
                </div>
            </div>

            <h3 class="text-[16px] font-bold text-accent mb-1">{{ $c['title'] }}</h3>
            <p class="text-[12px] text-muted mb-3">{{ __('contracts.supplier') }}: {{ $c['supplier'] }}</p>

            <div class="grid grid-cols-2 gap-4 text-[11px] text-muted mb-3">
                <span>{{ __('common.started') }}: {{ $c['started'] }}</span>
                <span class="text-end">{{ __('common.expected') }}: {{ $c['expected'] }}</span>
            </div>

            <div>
                <div class="flex items-center justify-between text-[11px] text-muted mb-1">
                    <span>{{ $c['progress_label'] }}</span>
                    <span>{{ $c['progress'] }}%</span>
                </div>
                <div class="w-full h-2 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ $c['progress'] }}%; background: {{ $c['progress_color'] }};"></div>
                </div>
            </div>
        </a>
        @empty
        @if(request('q') || $statusFilter !== 'all')
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
    <div class="mt-6">
        {{ $paginator->onEachSide(1)->links() }}
    </div>
    @endif
</div>

@endsection
