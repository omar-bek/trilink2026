@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.icv_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.icv_title')" :subtitle="__('gov.icv_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total_certs'])" :label="__('gov.total_certificates')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['verified'])" :label="__('gov.verified')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="$stats['avg_score'] . '%'" :label="__('gov.avg_icv_score')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['expiring_soon'])" :label="__('gov.expiring_60d')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
</div>

<div class="flex items-center gap-3 mb-4 justify-end">
    <a href="{{ route('gov.export', ['type' => 'icv']) }}" class="px-4 h-10 rounded-xl text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        {{ __('common.export') }} CSV
    </a>
</div>

{{-- By Issuer --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.by_issuer') }}</h3>
        <div class="space-y-3">
            @foreach($byIssuer as $i)
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="text-[13px] font-semibold text-primary uppercase">{{ $i->issuer }}</span>
                    <div class="flex-1 h-2 bg-page rounded-full overflow-hidden">
                        @php $iPct = $stats['verified'] > 0 ? ($i->count / $stats['verified']) * 100 : 0; @endphp
                        <div class="h-full bg-accent rounded-full" style="width: {{ $iPct }}%"></div>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-[13px] font-bold text-primary">{{ $i->count }}</span>
                    <span class="text-[11px] text-muted ml-1">({{ round($i->avg_score, 1) }}%)</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Score distribution --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.score_distribution') }}</h3>
        <div class="space-y-3">
            @php $scoreColors = ['80-100'=>'#00d9b5','60-79'=>'#4f7cff','40-59'=>'#ffb020','20-39'=>'#ff4d7f','0-19'=>'#ef4444']; @endphp
            @foreach($scoreDistribution as $sd)
            <div class="flex items-center justify-between gap-3">
                <span class="text-[13px] font-semibold text-primary min-w-[60px]">{{ $sd->bracket }}%</span>
                <div class="flex-1 h-3 bg-page rounded-full overflow-hidden">
                    @php $sdMax = $scoreDistribution->max('count') ?: 1; @endphp
                    <div class="h-full rounded-full" style="width: {{ ($sd->count / $sdMax) * 100 }}%; background: {{ $scoreColors[$sd->bracket] ?? '#525252' }};"></div>
                </div>
                <span class="text-[13px] font-bold text-primary min-w-[30px] text-right">{{ $sd->count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
