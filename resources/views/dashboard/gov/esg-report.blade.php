@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.esg_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.esg_title')" :subtitle="__('gov.esg_subtitle')" :back="route('gov.index')" />

{{-- ESG overview --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($esgStats['companies_with_esg'])" :label="__('gov.companies_with_esg')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>' />
    <x-dashboard.stat-card :value="$esgStats['avg_environmental']" :label="__('gov.environmental')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>' />
    <x-dashboard.stat-card :value="$esgStats['avg_social']" :label="__('gov.social')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>' />
    <x-dashboard.stat-card :value="$esgStats['avg_governance']" :label="__('gov.governance')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/>' />
    <x-dashboard.stat-card :value="$esgStats['avg_overall']" :label="__('gov.overall_esg')" color="slate"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>' />
</div>

{{-- Carbon + Compliance grid --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Carbon footprint --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.carbon_footprint') }}</h3>
        <div class="text-center mb-4">
            <p class="text-[36px] font-bold text-primary">{{ number_format($carbonStats['total_co2e_tonnes'], 1) }}</p>
            <p class="text-[13px] text-muted">{{ __('gov.tonnes_co2e') }}</p>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div class="text-center p-3 bg-page rounded-xl">
                <p class="text-[18px] font-bold text-[#00d9b5]">{{ number_format($carbonStats['scope1'], 1) }}</p>
                <p class="text-[11px] text-muted">Scope 1</p>
            </div>
            <div class="text-center p-3 bg-page rounded-xl">
                <p class="text-[18px] font-bold text-[#4f7cff]">{{ number_format($carbonStats['scope2'], 1) }}</p>
                <p class="text-[11px] text-muted">Scope 2</p>
            </div>
            <div class="text-center p-3 bg-page rounded-xl">
                <p class="text-[18px] font-bold text-[#8B5CF6]">{{ number_format($carbonStats['scope3'], 1) }}</p>
                <p class="text-[11px] text-muted">Scope 3</p>
            </div>
        </div>
    </div>

    {{-- Grade distribution --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.esg_grades') }}</h3>
        @php $gradeColors = ['A'=>'#00d9b5','B'=>'#4f7cff','C'=>'#ffb020','D'=>'#ff4d7f','F'=>'#ef4444']; @endphp
        <div class="space-y-3">
            @php $gradeMax = max(($gradeDistribution->max('count') ?? 1), 1); @endphp
            @foreach($gradeDistribution as $g)
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-[14px] font-bold text-white" style="background: {{ $gradeColors[$g->grade] ?? '#525252' }};">{{ $g->grade }}</span>
                <div class="flex-1 h-3 bg-page rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width: {{ ($g->count / $gradeMax) * 100 }}%; background: {{ $gradeColors[$g->grade] ?? '#525252' }};"></div>
                </div>
                <span class="text-[13px] font-bold text-primary min-w-[30px] text-right">{{ $g->count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Supply chain compliance --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('gov.modern_slavery') }}</h3>
        <div class="grid grid-cols-2 gap-3">
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[24px] font-bold text-primary">{{ $slaveryStats['statements_filed'] }}</p>
                <p class="text-[11px] text-muted">{{ __('gov.statements_filed') }}</p>
            </div>
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[24px] font-bold text-[#00d9b5]">{{ $slaveryStats['board_approved'] }}</p>
                <p class="text-[11px] text-muted">{{ __('gov.board_approved') }}</p>
            </div>
        </div>
    </div>

    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('gov.conflict_minerals') }}</h3>
        <div class="grid grid-cols-2 gap-3">
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[24px] font-bold text-primary">{{ $mineralsStats['declarations'] }}</p>
                <p class="text-[11px] text-muted">{{ __('gov.declarations') }}</p>
            </div>
            <div class="text-center p-4 bg-page rounded-xl">
                <p class="text-[24px] font-bold text-[#00d9b5]">{{ $mineralsStats['conflict_free'] }}</p>
                <p class="text-[11px] text-muted">{{ __('gov.conflict_free') }}</p>
            </div>
        </div>
    </div>
</div>

@endsection
