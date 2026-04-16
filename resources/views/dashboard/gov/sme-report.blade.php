@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.sme_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.sme_title')" :subtitle="__('gov.sme_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($totalCompanies)" :label="__('gov.total_companies')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>' />
    <x-dashboard.stat-card :value="number_format($smeCount)" :label="__('gov.sme_companies')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z"/>' />
    <x-dashboard.stat-card :value="number_format($largeCount)" :label="__('gov.large_companies')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/>' />
    @php $smePct = $totalCompanies > 0 ? round(($smeCount / $totalCompanies) * 100, 1) : 0; @endphp
    <x-dashboard.stat-card :value="$smePct . '%'" :label="__('gov.sme_percentage')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>' />
</div>

{{-- SME vs Large contract value --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.contract_value_split') }}</h3>
        @php $smeValPct = $totalContractValue > 0 ? round(($smeContractValue / $totalContractValue) * 100, 1) : 0; @endphp
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-1 h-6 bg-page rounded-full overflow-hidden flex">
                <div class="h-full bg-[#00d9b5]" style="width: {{ $smeValPct }}%"></div>
                <div class="h-full bg-[#8B5CF6]" style="width: {{ 100 - $smeValPct }}%"></div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[20px] font-bold text-[#00d9b5]">AED {{ number_format($smeContractValue) }}</p>
                <p class="text-[11px] text-muted">SME ({{ $smeValPct }}%)</p>
            </div>
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[20px] font-bold text-[#8B5CF6]">AED {{ number_format($largeContractValue) }}</p>
                <p class="text-[11px] text-muted">Large ({{ round(100 - $smeValPct, 1) }}%)</p>
            </div>
        </div>
    </div>

    {{-- By type --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.by_company_type') }}</h3>
        <div class="space-y-3">
            @php $typeMax = max(($byType->max('count') ?? 1), 1); $typeColors = ['buyer'=>'#4f7cff','supplier'=>'#00d9b5','both'=>'#8B5CF6']; @endphp
            @foreach($byType as $t)
            <div class="flex items-center gap-3">
                <span class="text-[13px] font-semibold text-primary capitalize min-w-[80px]">{{ $t->type }}</span>
                <div class="flex-1 h-3 bg-page rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width: {{ ($t->count / $typeMax) * 100 }}%; background: {{ $typeColors[$t->type] ?? '#525252' }};"></div>
                </div>
                <span class="text-[13px] font-bold text-primary min-w-[30px] text-right">{{ $t->count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Registration trend --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.registration_trend') }}</h3>
    <div class="flex items-center gap-3 mb-2 justify-end">
        <a href="{{ route('gov.export', ['type' => 'companies']) }}" class="px-3 h-8 rounded-lg text-[11px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            CSV
        </a>
    </div>
    <div class="overflow-x-auto">
        <div class="flex items-end gap-2 h-[160px] min-w-[500px]">
            @php $maxReg = $registrationTrend->max('count') ?: 1; @endphp
            @foreach($registrationTrend as $m)
            @php $pct = ($m->count / $maxReg) * 100; @endphp
            <div class="flex-1 flex flex-col items-center gap-1">
                <span class="text-[10px] text-muted font-mono">{{ $m->count }}</span>
                <div class="w-full rounded-t-lg bg-[#00d9b5]/60" style="height: {{ max($pct, 2) }}%"></div>
                <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month.'-01')->format('M') }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
