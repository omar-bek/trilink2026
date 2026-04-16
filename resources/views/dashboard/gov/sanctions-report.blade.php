@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.sanctions_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.sanctions_title')" :subtitle="__('gov.sanctions_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total_screenings'])" :label="__('gov.total_screenings')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['clean'])" :label="__('gov.clean')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['hits'])" :label="__('gov.hits')" color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['reviews'])" :label="__('gov.under_review')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['errors'])" :label="__('gov.errors')" color="slate"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 0h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
</div>

{{-- Screening trend --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.screening_trend') }}</h3>
    <div class="overflow-x-auto">
        <div class="flex items-end gap-2 h-[180px] min-w-[600px]">
            @php $maxTotal = $screeningTrend->max('total') ?: 1; @endphp
            @foreach($screeningTrend as $m)
            @php $pct = ($m->total / $maxTotal) * 100; @endphp
            <div class="flex-1 flex flex-col items-center gap-1">
                <span class="text-[9px] text-muted font-mono">{{ $m->total }}</span>
                <div class="w-full rounded-t-lg relative" style="height: {{ max($pct, 2) }}%">
                    <div class="absolute inset-0 rounded-t-lg bg-accent/50"></div>
                    @if($m->hits > 0)
                    <div class="absolute bottom-0 left-0 right-0 rounded-t-sm bg-[#ff4d7f]/70" style="height: {{ ($m->hits / $m->total) * 100 }}%"></div>
                    @endif
                </div>
                <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month.'-01')->format('M') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    <div class="flex items-center gap-4 mt-3 text-[11px] text-muted">
        <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-accent/50"></span>{{ __('gov.total_screenings') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-[#ff4d7f]/70"></span>{{ __('gov.hits') }}</span>
    </div>
</div>

{{-- Recent hits --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.recent_hits') }}</h3>
    <div class="space-y-3">
        @forelse($recentHits as $h)
        @php $hColor = $h->result === 'hit' ? '#ff4d7f' : '#ffb020'; @endphp
        <div class="bg-page border border-th-border rounded-xl p-4 flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center h-[20px] px-2 rounded-full text-[10px] font-bold uppercase" style="background:{{ $hColor }}1A;border:1px solid {{ $hColor }}33;color:{{ $hColor }};">{{ $h->result }}</span>
                    <span class="text-[13px] font-semibold text-primary">{{ $h->company?->name ?? '—' }}</span>
                </div>
                <p class="text-[12px] text-muted">{{ $h->match_count }} match(es) &middot; Provider: {{ $h->provider ?? '—' }}</p>
            </div>
            <span class="text-[11px] text-muted flex-shrink-0">{{ $h->created_at?->format('d M Y') }}</span>
        </div>
        @empty
        <div class="text-center py-8">
            <p class="text-[13px] text-muted">{{ __('gov.no_hits') }}</p>
        </div>
        @endforelse
    </div>
</div>

@endsection
