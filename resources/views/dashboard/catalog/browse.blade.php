@extends('layouts.dashboard', ['active' => 'catalog'])
@section('title', __('catalog.marketplace'))

@section('content')

<x-dashboard.page-header :title="__('catalog.marketplace')" :subtitle="__('catalog.marketplace_subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">{{ session('status') }}</div>
@endif
@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- Phase 4 / Sprint 18 — Filter bar. Stacks query + category +
     price range + country + verification level + in-stock toggle. --}}
<form method="GET" action="{{ route('dashboard.catalog.browse') }}" class="mb-6 bg-surface border border-th-border rounded-2xl p-4">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <input type="search" name="q" value="{{ $query }}" placeholder="{{ __('catalog.search_placeholder') }}"
               class="md:col-span-5 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <select name="category_id"
                class="md:col-span-3 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('catalog.all_categories') }}</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected($catId == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="country"
                class="md:col-span-2 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('catalog.all_countries') }}</option>
            @foreach($countries as $cn)
                <option value="{{ $cn }}" @selected($country == $cn)>{{ $cn }}</option>
            @endforeach
        </select>
        <select name="verification"
                class="md:col-span-2 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('catalog.any_verification') }}</option>
            <option value="bronze"  @selected($verifLevel === 'bronze')>{{ __('catalog.tier_bronze') }}</option>
            <option value="silver"  @selected($verifLevel === 'silver')>{{ __('catalog.tier_silver') }}</option>
            <option value="gold"    @selected($verifLevel === 'gold')>{{ __('catalog.tier_gold') }}</option>
        </select>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-3">
        <input type="number" step="0.01" min="0" name="price_min" value="{{ $priceMin }}" placeholder="{{ __('catalog.price_min') }}"
               class="md:col-span-3 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <input type="number" step="0.01" min="0" name="price_max" value="{{ $priceMax }}" placeholder="{{ __('catalog.price_max') }}"
               class="md:col-span-3 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <label class="md:col-span-3 inline-flex items-center gap-2 text-[12px] text-primary px-3 py-2 bg-surface-2 border border-th-border rounded-lg">
            <input type="hidden" name="in_stock" value="0">
            <input type="checkbox" name="in_stock" value="1" @checked($inStock)>
            {{ __('catalog.filter_in_stock') }}
        </label>
        <button type="submit" class="md:col-span-3 inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            {{ __('catalog.apply_filters') }}
        </button>
    </div>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    @forelse($products as $p)
    @php
        $low = $p->lowestPrice();
        $hasVariants = $p->relationLoaded('variants') && $p->variants->isNotEmpty();
        $primary = collect($p->images ?? [])->first();
        $primaryUrl = $primary ? \Illuminate\Support\Facades\Storage::disk('public')->url($primary) : null;
    @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/40 hover:bg-surface-2 transition-all hover:-translate-y-0.5 hover:shadow-[0_18px_40px_-20px_rgba(79,124,255,0.35)]">
        <a href="{{ route('dashboard.catalog.show', $p->id) }}" class="block">
            <div class="aspect-video rounded-lg bg-surface-2 mb-4 overflow-hidden flex items-center justify-center text-muted">
                @if($primaryUrl)
                    <img src="{{ $primaryUrl }}" alt="{{ $p->name }}" loading="lazy" class="w-full h-full object-cover">
                @else
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/>
                    </svg>
                @endif
            </div>
            <div class="text-[11px] text-muted mb-1">{{ $p->category?->name ?? __('catalog.uncategorized') }}</div>
            <div class="text-[14px] font-bold text-primary mb-1 line-clamp-2">{{ $p->name }}</div>
            <div class="text-[11px] text-muted mb-3 truncate">{{ $p->company?->name ?? '—' }}@if($p->company?->country) · {{ $p->company->country }}@endif</div>
            <div class="flex items-center justify-between mb-3">
                <div>
                    @if($hasVariants && $low < (float) $p->base_price)
                        <div class="text-[10px] text-muted uppercase tracking-wide">{{ __('catalog.from') }}</div>
                    @endif
                    <div class="text-[16px] font-bold text-[#00d9b5]">{{ number_format($low, 2) }} {{ $p->currency }}</div>
                    <div class="text-[10px] text-muted">{{ __('catalog.per') }} {{ $p->unit }}</div>
                </div>
                <div class="text-end">
                    <div class="text-[10px] text-muted">{{ __('catalog.lead_time') }}</div>
                    <div class="text-[12px] font-semibold text-primary">{{ $p->lead_time_days }} {{ __('catalog.days') }}</div>
                </div>
            </div>
        </a>
        @auth
        @if(auth()->user()->company_id !== $p->company_id && !$hasVariants)
        <form method="POST" action="{{ route('dashboard.cart.add') }}" class="flex gap-2">
            @csrf
            <input type="hidden" name="product_id" value="{{ $p->id }}">
            <input type="hidden" name="quantity" value="{{ $p->min_order_qty }}">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 h-9 rounded-lg bg-accent/10 border border-accent/30 text-accent text-[12px] font-semibold hover:bg-accent/20 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                {{ __('catalog.add_to_cart') }}
            </button>
        </form>
        @elseif($hasVariants && auth()->user()->company_id !== $p->company_id)
        <a href="{{ route('dashboard.catalog.show', $p->id) }}"
           class="block text-center h-9 leading-9 rounded-lg bg-accent/10 border border-accent/30 text-accent text-[12px] font-semibold hover:bg-accent/20 transition-colors">
            {{ __('catalog.choose_variant') }}
        </a>
        @endif
        @endauth
    </div>
    @empty
    <div class="col-span-full bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-4 text-accent">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6"/></svg>
        </div>
        <p class="text-[14px] sm:text-[15px] font-bold text-primary">{{ __('catalog.no_products_found') }}</p>
        <p class="text-[12.5px] text-muted mt-1">{{ __('catalog.no_products_hint') ?? __('common.try_different_filters') }}</p>
    </div>
    @endforelse
</div>

<div class="mt-6">{{ $products->links() }}</div>

@endsection
