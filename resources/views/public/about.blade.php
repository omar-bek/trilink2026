@extends('layouts.app')

@section('content')
<x-landing.navbar />

<div class="min-h-screen bg-page pt-28 pb-20">
    <div class="max-w-5xl mx-auto px-6 lg:px-10">

        {{-- Hero --}}
        <div class="text-center mb-16">
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-16 w-auto mx-auto mb-6 dark:brightness-100 brightness-0" />
            <h1 class="text-[36px] sm:text-[48px] font-bold text-primary leading-tight mb-4">{{ __('about.title') }}</h1>
            <p class="text-[16px] text-muted max-w-2xl mx-auto leading-relaxed">{{ __('about.subtitle') }}</p>
        </div>

        {{-- Mission --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            @php
            $values = [
                ['icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'title' => __('about.trust'), 'desc' => __('about.trust_desc'), 'color' => '#4f7cff'],
                ['icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', 'title' => __('about.efficiency'), 'desc' => __('about.efficiency_desc'), 'color' => '#00d9b5'],
                ['icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z', 'title' => __('about.compliance'), 'desc' => __('about.compliance_desc'), 'color' => '#ff4d7f'],
            ];
            @endphp
            @foreach($values as $v)
            <div class="bg-surface border border-th-border rounded-2xl p-8 text-center">
                <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background: {{ $v['color'] }}15; border: 1px solid {{ $v['color'] }}30;">
                    <svg class="w-7 h-7" style="color: {{ $v['color'] }}" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $v['icon'] }}"/></svg>
                </div>
                <h3 class="text-[18px] font-bold text-primary mb-2">{{ $v['title'] }}</h3>
                <p class="text-[14px] text-muted leading-relaxed">{{ $v['desc'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Stats --}}
        <div class="bg-surface border border-th-border rounded-3xl p-10 mb-16">
            <h2 class="text-[24px] font-bold text-primary text-center mb-8">{{ __('about.platform_stats') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <p class="text-[36px] font-bold text-accent">20+</p>
                    <p class="text-[13px] text-muted">{{ __('about.free_zones') }}</p>
                </div>
                <div>
                    <p class="text-[36px] font-bold text-[#00d9b5]">6</p>
                    <p class="text-[13px] text-muted">{{ __('about.vat_treatments') }}</p>
                </div>
                <div>
                    <p class="text-[36px] font-bold text-[#ffb020]">3</p>
                    <p class="text-[13px] text-muted">{{ __('about.signature_grades') }}</p>
                </div>
                <div>
                    <p class="text-[36px] font-bold text-[#ff4d7f]">8</p>
                    <p class="text-[13px] text-muted">{{ __('about.uae_laws') }}</p>
                </div>
            </div>
        </div>

        {{-- Compliance --}}
        <div class="text-center mb-16">
            <h2 class="text-[24px] font-bold text-primary mb-4">{{ __('about.compliance_title') }}</h2>
            <p class="text-[14px] text-muted max-w-2xl mx-auto mb-8">{{ __('about.compliance_subtitle') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                @foreach(['PDPL (FDL 45/2021)', 'E-Signatures (FDL 46/2021)', 'Corporate Tax (FDL 47/2022)', 'Trade License (FDL 50/2022)', 'Competition (FDL 36/2023)', 'VAT (FDL 8/2017)', 'Peppol E-Invoice', 'DIFC / ADGM'] as $badge)
                <span class="inline-flex items-center px-4 py-2 rounded-full text-[12px] font-semibold bg-accent/10 text-accent border border-accent/20">{{ $badge }}</span>
                @endforeach
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center">
            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-8 py-4 rounded-2xl text-[15px] font-bold text-white bg-accent hover:bg-accent-h shadow-[0_8px_24px_rgba(79,124,255,0.3)] transition-all hover:scale-[1.02]">
                {{ __('about.get_started') }}
                <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>
    </div>
</div>

<x-landing.footer />
@endsection
