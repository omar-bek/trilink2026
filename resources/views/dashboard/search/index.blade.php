@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', __('search.title'))

@section('content')

<x-dashboard.page-header :title="__('search.title')" :subtitle="__('search.subtitle')" />

{{-- Search bar --}}
<form method="GET" action="{{ route('dashboard.search') }}"
      class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <div class="flex gap-2 sm:gap-3 items-center">
        <div class="flex-1 relative">
            <svg class="w-4 h-4 text-faint absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            <input type="text" name="q" value="{{ $q }}" autofocus
                   placeholder="{{ __('search.placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition">
        </div>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 px-4 sm:px-5 h-12 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            <span class="hidden sm:inline">{{ __('common.search') }}</span>
        </button>
    </div>
</form>

@if($q === '' && $recent_searches->isNotEmpty())
{{-- Recent searches (Phase 1 / task 1.13) — only when no active query. --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    <h3 class="text-[14px] font-bold text-primary mb-3">{{ __('search.recent') }}</h3>
    <div class="flex flex-wrap gap-2">
        @foreach($recent_searches as $entry)
        <a href="{{ route('dashboard.search', ['q' => $entry->term]) }}"
           class="inline-flex items-center gap-2 px-3 h-8 rounded-full text-[12px] text-body bg-page border border-th-border hover:border-accent/40 transition-colors">
            <svg class="w-3 h-3 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $entry->term }}
        </a>
        @endforeach
    </div>
</div>
@endif

@if($q !== '')
<div class="mb-4">
    <p class="text-[13px] text-muted">{{ __('search.results_for', ['count' => $results['total'], 'term' => $q]) }}</p>
</div>

{{-- RFQs section --}}
@if($results['rfqs']->isNotEmpty())
<section class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-[16px] font-bold text-primary">{{ __('nav.rfqs') }} ({{ $results['rfqs']->count() }})</h3>
        <a href="{{ route('dashboard.rfqs', ['q' => $q]) }}" class="text-[12px] text-accent hover:underline">{{ __('common.view_all') ?? 'View all' }}</a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        @foreach($results['rfqs'] as $rfq)
        <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq->id]) }}"
           class="block bg-surface border border-th-border rounded-xl p-4 hover:border-accent/40 transition-all">
            <div class="flex items-start justify-between gap-3 mb-1">
                <span class="text-[11px] font-mono text-muted">#{{ $rfq->rfq_number }}</span>
                <span class="text-[12px] font-bold text-accent">{{ number_format((float) $rfq->budget) }} {{ $rfq->currency ?? 'AED' }}</span>
            </div>
            <h4 class="text-[14px] font-bold text-primary line-clamp-1">{{ $rfq->title }}</h4>
            <p class="text-[11px] text-muted mt-1">{{ $rfq->company?->name }} · {{ $rfq->category?->name ?? __('rfq.uncategorized') }}</p>
        </a>
        @endforeach
    </div>
</section>
@endif

{{-- Products section --}}
@if($results['products']->isNotEmpty())
<section class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-[16px] font-bold text-primary">{{ __('catalog.title') ?? 'Products' }} ({{ $results['products']->count() }})</h3>
        <a href="{{ route('dashboard.catalog.browse') ?? '#' }}" class="text-[12px] text-accent hover:underline">{{ __('common.view_all') ?? 'View all' }}</a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        @foreach($results['products'] as $product)
        <div class="block bg-surface border border-th-border rounded-xl p-4">
            <h4 class="text-[14px] font-bold text-primary line-clamp-1">{{ $product->name }}</h4>
            <p class="text-[11px] text-muted mt-1">{{ $product->company?->name }} · {{ $product->sku }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- Suppliers section --}}
@if($results['suppliers']->isNotEmpty())
<section class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-[16px] font-bold text-primary">{{ __('directory.title') }} ({{ $results['suppliers']->count() }})</h3>
        <a href="{{ route('dashboard.suppliers.directory', ['q' => $q]) }}" class="text-[12px] text-accent hover:underline">{{ __('common.view_all') ?? 'View all' }}</a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        @foreach($results['suppliers'] as $supplier)
        <a href="{{ route('dashboard.suppliers.profile', ['id' => $supplier->id]) }}"
           class="block bg-surface border border-th-border rounded-xl p-4 hover:border-accent/40 transition-all">
            <div class="flex items-start justify-between gap-2 mb-1">
                <h4 class="text-[14px] font-bold text-primary line-clamp-1">{{ $supplier->name }}</h4>
                <x-dashboard.verification-badge :level="$supplier->verification_level" />
            </div>
            @if($supplier->country)
            <p class="text-[11px] text-muted mt-0.5">{{ $supplier->country }}</p>
            @endif
        </a>
        @endforeach
    </div>
</section>
@endif

@if($results['total'] === 0)
<x-dashboard.empty-state
    :title="__('search.empty_title')"
    :message="__('search.empty_message')" />
@endif

@endif

@endsection
