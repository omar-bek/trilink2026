@extends('layouts.dashboard', ['active' => 'suppliers'])
@section('title', __('directory.browse_categories'))

@section('content')

<x-dashboard.page-header
    :title="__('directory.browse_categories')"
    :subtitle="__('directory.browse_categories_subtitle')" />

{{-- Breadcrumbs (UNSPSC drill-down: Segment → Family → Class → Commodity) --}}
<nav class="flex items-center gap-2 text-[12px] text-muted mb-6 flex-wrap">
    <a href="{{ route('dashboard.categories.browse') }}"
       class="hover:text-accent transition-colors">{{ __('directory.all_segments') }}</a>
    @foreach($breadcrumbs as $crumb)
        <span class="text-faint">/</span>
        <a href="{{ route('dashboard.categories.browse', ['root' => $crumb->id]) }}"
           class="{{ $loop->last ? 'text-primary font-semibold' : 'hover:text-accent' }} transition-colors">
            @if($crumb->unspsc_code)
                <span class="font-mono text-[10px] text-faint me-1">{{ $crumb->unspsc_code }}</span>
            @endif
            {{ $crumb->name }}
        </a>
    @endforeach
</nav>

@if($root)
<div class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-primary">{{ $root->name }}</h2>
            @if($root->name_ar)
                <p class="text-[14px] text-muted mt-0.5">{{ $root->name_ar }}</p>
            @endif
            @if($root->unspsc_code)
                <p class="text-[11px] font-mono text-faint mt-1">UNSPSC {{ $root->unspsc_code }}</p>
            @endif
        </div>
        <a href="{{ route('dashboard.suppliers.directory', ['category' => $root->id]) }}"
           class="inline-flex items-center gap-2 px-4 h-10 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
            {{ __('directory.view_suppliers') }}
            <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
        </a>
    </div>
</div>
@endif

{{-- Children grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($children as $child)
    <a href="{{ route('dashboard.categories.browse', ['root' => $child->id]) }}"
       class="block bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/40 hover:shadow-lg transition-all">
        <div class="flex items-start justify-between gap-3 mb-2">
            <h3 class="text-[15px] font-bold text-primary leading-tight">{{ $child->name }}</h3>
            @if($child->companies_count > 0)
                <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-[10px] font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20">
                    {{ $child->companies_count }} {{ __('directory.suppliers_short') }}
                </span>
            @endif
        </div>
        @if($child->name_ar)
            <p class="text-[12px] text-muted mb-2">{{ $child->name_ar }}</p>
        @endif
        @if($child->unspsc_code)
            <p class="text-[10px] font-mono text-faint">{{ $child->unspsc_code }}</p>
        @endif
    </a>
    @empty
    <div class="md:col-span-2 lg:col-span-3">
        <x-dashboard.empty-state
            :title="__('directory.no_subcategories')"
            :message="__('directory.no_subcategories_message')"
            :cta="__('directory.view_suppliers')"
            :ctaUrl="$root ? route('dashboard.suppliers.directory', ['category' => $root->id]) : route('dashboard.suppliers.directory')" />
    </div>
    @endforelse
</div>

@endsection
