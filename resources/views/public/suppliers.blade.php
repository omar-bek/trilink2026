@extends('layouts.app')
@section('title', __('public_directory.title') . ' · ' . config('app.name'))

@section('content')

{{-- Top nav --}}
<header class="border-b border-th-border bg-page/90 backdrop-blur sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="text-[18px] font-bold text-primary">{{ config('app.name') }}</a>
        <nav class="flex items-center gap-4">
            <a href="{{ route('public.suppliers') }}" class="text-[13px] font-semibold text-accent">{{ __('public_directory.nav') }}</a>
            <a href="{{ route('login') }}" class="text-[13px] text-muted hover:text-primary">{{ __('auth.login') }}</a>
            <a href="{{ route('register') }}" class="inline-flex items-center px-4 h-9 rounded-lg text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                {{ __('auth.join') }}
            </a>
        </nav>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-12">
    {{-- Hero --}}
    <div class="mb-10 text-center">
        <h1 class="text-[40px] sm:text-[56px] font-bold text-primary leading-tight tracking-tight">
            {{ __('public_directory.hero_title') }}
        </h1>
        <p class="text-[16px] text-muted mt-3 max-w-2xl mx-auto">
            {{ __('public_directory.hero_subtitle') }}
        </p>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('public.suppliers') }}"
          class="bg-surface border border-th-border rounded-2xl p-4 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-2 relative">
                <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="{{ $filters['q'] }}"
                       placeholder="{{ __('public_directory.search_placeholder') }}"
                       class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
            </div>
            <select name="category"
                    class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
                <option value="">{{ __('directory.all_categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($filters['category'] == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <select name="country"
                    class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
                <option value="">{{ __('directory.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                @endforeach
            </select>
            {{-- Phase 4 (UAE Compliance Roadmap) — minimum ICV filter.
                 Government-adjacent buyers can narrow the directory to
                 suppliers with a usable in-country value score above
                 the chosen threshold. --}}
            <select name="icv_min"
                    class="bg-page border border-th-border rounded-xl px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50">
                <option value="">{{ __('public_directory.icv_any') }}</option>
                @foreach([20, 30, 40, 50, 60, 70] as $threshold)
                    <option value="{{ $threshold }}" @selected((int) ($filters['icv_min'] ?? 0) === $threshold)>{{ __('public_directory.icv_min_label', ['n' => $threshold]) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex justify-end mt-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 h-10 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                {{ __('common.search') }}
            </button>
        </div>
    </form>

    {{-- Result count --}}
    <p class="text-[13px] text-muted mb-4">{{ __('public_directory.results', ['count' => $total]) }}</p>

    {{-- Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($companies as $supplier)
        <a href="{{ route('login') }}?intended={{ urlencode(route('dashboard.suppliers.profile', ['id' => $supplier->id])) }}"
           class="block bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/40 hover:shadow-lg transition-all">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div class="min-w-0 flex-1">
                    <h3 class="text-[16px] font-bold text-primary truncate">{{ $supplier->name }}</h3>
                    @if($supplier->country)
                    <p class="text-[11px] text-muted mt-0.5">{{ $supplier->country }}</p>
                    @endif
                </div>
                <x-dashboard.verification-badge :level="$supplier->verification_level" />
            </div>

            {{-- Phase 4 (UAE Compliance Roadmap) — ICV badge. Shows the
                 supplier's best active ICV score so government-adjacent
                 buyers can spot eligible candidates without opening
                 each profile. Pulled from the eager-loaded relation. --}}
            @php $bestIcv = $supplier->icvCertificates?->first(); @endphp
            @if($bestIcv)
                <div class="mb-3">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full bg-accent/10 border border-accent/20 text-accent">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ICV {{ rtrim(rtrim(number_format((float) $bestIcv->score, 2), '0'), '.') }}%
                    </span>
                </div>
            @endif

            {{-- Phase 3 (UAE Compliance Roadmap) — Free Zone & jurisdiction
                 badges. Free zone authority + DIFC/ADGM tag let buyers
                 filter on legal & VAT classification at a glance. --}}
            @if($supplier->is_free_zone || $supplier->jurisdiction()?->value !== 'federal')
                <div class="flex flex-wrap gap-1.5 mb-3">
                    @if($supplier->is_free_zone && $supplier->free_zone_authority)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-accent/10 border border-accent/20 text-accent">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $supplier->free_zone_authority->label() }}
                        </span>
                    @endif
                    @if($supplier->is_designated_zone)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 text-[#00d9b5]">
                            {{ __('public_directory.designated_zone') }}
                        </span>
                    @endif
                    @if($supplier->jurisdiction()?->value !== 'federal')
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 text-[#8B5CF6]">
                            {{ $supplier->jurisdiction()->label() }}
                        </span>
                    @endif
                </div>
            @endif

            @if($supplier->description)
            <p class="text-[12px] text-muted line-clamp-2 mb-3">{{ $supplier->description }}</p>
            @endif

            @if($supplier->categories->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach($supplier->categories->take(3) as $cat)
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-page border border-th-border text-muted">{{ $cat->name }}</span>
                @endforeach
            </div>
            @endif
        </a>
        @empty
        <div class="md:col-span-2 lg:col-span-3 text-center py-20">
            <p class="text-[15px] text-muted">{{ __('public_directory.empty') }}</p>
        </div>
        @endforelse
    </div>

    {{-- CTA strip --}}
    <div class="mt-16 bg-surface border border-th-border rounded-2xl p-8 text-center">
        <h2 class="text-[24px] font-bold text-primary mb-2">{{ __('public_directory.cta_title') }}</h2>
        <p class="text-[14px] text-muted mb-5 max-w-xl mx-auto">{{ __('public_directory.cta_subtitle') }}</p>
        <a href="{{ route('register') }}"
           class="inline-flex items-center gap-2 px-6 h-12 rounded-xl text-[14px] font-bold text-white bg-accent hover:bg-accent-h">
            {{ __('auth.join') }}
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
        </a>
    </div>
</main>

@endsection
