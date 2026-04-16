@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.collusion_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.collusion_title')" :subtitle="__('gov.collusion_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total_alerts'])" :label="__('gov.total_alerts')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['open'])" :label="__('gov.open_alerts')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['confirmed'])" :label="__('gov.confirmed_collusion')" color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 0h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['investigating'])" :label="__('gov.under_investigation')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>' />
</div>

{{-- By severity & type --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.by_severity') }}</h3>
        @php $sevColors = ['critical'=>'#ef4444','high'=>'#ff4d7f','medium'=>'#ffb020']; @endphp
        <div class="space-y-3">
            @foreach(['critical','high','medium'] as $sev)
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold uppercase tracking-wider" style="background: {{ $sevColors[$sev] }}1A; border: 1px solid {{ $sevColors[$sev] }}33; color: {{ $sevColors[$sev] }};">{{ $sev }}</span>
                <div class="flex-1 h-2 bg-page rounded-full overflow-hidden mx-3">
                    @php $sevMax = max(($bySeverity->max() ?? 1), 1); $sevCount = $bySeverity[$sev] ?? 0; @endphp
                    <div class="h-full rounded-full" style="width: {{ ($sevCount / $sevMax) * 100 }}%; background: {{ $sevColors[$sev] }};"></div>
                </div>
                <span class="text-[14px] font-bold text-primary">{{ $sevCount }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.by_type') }}</h3>
        <div class="space-y-3">
            @foreach($byType as $t)
            <div class="flex items-center justify-between gap-3">
                <span class="text-[13px] font-mono text-primary">{{ $t->type }}</span>
                <span class="text-[14px] font-bold text-primary">{{ $t->count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Recent alerts --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.recent_alerts') }}</h3>
    <div class="space-y-3">
        @foreach($recentAlerts as $a)
        @php $sc = $sevColors[$a->severity] ?? '#525252'; @endphp
        <div class="bg-page border border-th-border rounded-xl p-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center h-[20px] px-2 rounded-full text-[10px] font-bold uppercase" style="background:{{ $sc }}1A;border:1px solid {{ $sc }}33;color:{{ $sc }};">{{ $a->severity }}</span>
                <span class="text-[12px] font-mono text-muted">{{ $a->type }}</span>
                <span class="text-[11px] text-muted ml-auto">{{ \Carbon\Carbon::parse($a->created_at)->format('d M Y, H:i') }}</span>
            </div>
            <p class="text-[13px] text-primary font-semibold">RFQ {{ $a->rfq_number ?? '#'.$a->rfq_id }} — {{ $a->rfq_title ?? '' }}</p>
        </div>
        @endforeach
    </div>
</div>

@endsection
