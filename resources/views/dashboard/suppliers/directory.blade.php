@extends('layouts.dashboard', ['active' => 'suppliers'])
@section('title', __('directory.title'))

@section('content')

<x-dashboard.page-header :title="__('directory.title')" :subtitle="__('directory.subtitle')" />

{{-- Filter bar (GET form so URLs stay shareable / bookmarkable) --}}
<form method="GET" action="{{ route('dashboard.suppliers.directory') }}"
      class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">
        {{-- Free-text search --}}
        <div class="lg:col-span-2 relative">
            <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ __('directory.search_placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 h-11 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
        </div>

        {{-- Category --}}
        <select name="category"
                class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
            <option value="">{{ __('directory.all_categories') }}</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($filters['category'] == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>

        {{-- Country --}}
        <select name="country"
                class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
            <option value="">{{ __('directory.all_countries') }}</option>
            @foreach($countries as $country)
                <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
            @endforeach
        </select>

        {{-- Verification tier --}}
        <select name="verification"
                class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
            <option value="">{{ __('directory.all_tiers') }}</option>
            @foreach($verifications as $tier)
                <option value="{{ $tier->value }}" @selected($filters['verification'] === $tier->value)>{{ $tier->label() }}</option>
            @endforeach
        </select>

        {{-- Min rating --}}
        <select name="rating"
                class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
            <option value="0">{{ __('directory.any_rating') }}</option>
            @foreach([4.5, 4, 3.5, 3] as $r)
                <option value="{{ $r }}" @selected((float)$filters['rating'] === (float)$r)>{{ $r }}+ ★</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
        <label class="inline-flex items-center gap-2 text-[12px] text-muted cursor-pointer">
            <input type="checkbox" name="has_certs" value="1" @checked($filters['has_certs'])
                   class="rounded border-th-border bg-page text-accent focus:ring-accent">
            {{ __('directory.has_certs') }}
        </label>

        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard.suppliers.directory') }}"
               class="text-[12px] text-muted hover:text-primary px-2">{{ __('common.reset') }}</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 h-11 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
                {{ __('common.apply') }}
            </button>
        </div>
    </div>
</form>

{{-- Result count + Save this search button (Phase 1 / task 1.5) --}}
<div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
    <p class="text-[13px] text-muted">{{ __('directory.results_found', ['count' => $total]) }}</p>

    <div class="flex items-center gap-3">
        <p class="text-[11px] text-faint">{{ __('directory.sorted_by_match') }}</p>
        <form method="POST" action="{{ route('dashboard.saved-searches.store') }}"
              onsubmit="this.label.value = prompt('{{ __('saved_searches.name_prompt') }}', '{{ __('saved_searches.suggested_label') }}'); return !!this.label.value;">
            @csrf
            <input type="hidden" name="resource_type" value="suppliers">
            <input type="hidden" name="label" value="">
            @foreach($filters as $k => $v)
                @if($v !== '' && $v !== null && $v !== 0 && $v !== false)
                    <input type="hidden" name="filters[{{ $k }}]" value="{{ is_bool($v) ? (int) $v : $v }}">
                @endif
            @endforeach
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg text-[11px] font-semibold text-accent bg-accent/10 border border-accent/20 hover:bg-accent/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                {{ __('saved_searches.save_this_search') }}
            </button>
        </form>
    </div>
</div>

{{-- Supplier cards --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($cards as $card)
    <a href="{{ route('dashboard.suppliers.profile', ['id' => $card['id']]) }}"
       class="block bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/40 hover:shadow-lg transition-all">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-[16px] font-bold text-primary truncate">{{ $card['name'] }}</h3>
                @if($card['country'])
                <p class="text-[11px] text-muted mt-0.5">{{ $card['country'] }}</p>
                @endif
            </div>
            @if($card['match_score'] !== null)
            <span class="inline-flex shrink-0 items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                {{ $card['match_score'] >= 80 ? 'text-emerald-400 bg-emerald-500/10 border border-emerald-500/20' : ($card['match_score'] >= 50 ? 'text-amber-400 bg-amber-500/10 border border-amber-500/20' : 'text-muted bg-surface-2 border border-th-border') }}">
                {{ $card['match_score'] }}% match
            </span>
            @endif
        </div>

        @if($card['description'])
        <p class="text-[12px] text-muted line-clamp-2 mb-3 leading-relaxed">{{ $card['description'] }}</p>
        @endif

        {{-- Category chips (max 3) --}}
        @if($card['categories'])
        <div class="flex flex-wrap gap-1.5 mb-3">
            @foreach($card['categories'] as $catName)
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-surface-2 border border-th-border text-muted">{{ $catName }}</span>
            @endforeach
            @if($card['category_count'] > count($card['categories']))
            <span class="text-[10px] text-faint">+{{ $card['category_count'] - count($card['categories']) }}</span>
            @endif
        </div>
        @endif

        <div class="flex items-center justify-between gap-2 pt-3 border-t border-th-border">
            <div class="flex items-center gap-3 text-[11px] text-muted">
                @if($card['rating'])
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3 h-3 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    <span class="text-primary font-semibold">{{ $card['rating'] }}</span>
                    <span class="text-faint">({{ $card['review_count'] }})</span>
                </span>
                @else
                <span class="text-faint">{{ __('directory.no_reviews_yet') }}</span>
                @endif

                @if($card['has_certs'])
                <span class="inline-flex items-center gap-1 text-emerald-400">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('directory.certified') }}
                </span>
                @endif
            </div>

            @if($card['verification_label'])
            <x-dashboard.verification-badge :level="$card['verification']" />
            @endif
        </div>
    </a>
    @empty
    <div class="md:col-span-2 lg:col-span-3">
        <x-dashboard.empty-state
            :title="__('directory.empty_title')"
            :message="__('directory.empty_message')" />
    </div>
    @endforelse
</div>

@endsection
