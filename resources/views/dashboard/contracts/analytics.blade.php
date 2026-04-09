@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.analytics_title'))

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div class="min-w-0">
        <a href="{{ route('dashboard.contracts') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('contracts.title') }}
        </a>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('contracts.analytics_title') }}</h1>
        <p class="text-[14px] text-muted mt-1">{{ __('contracts.analytics_subtitle') }}</p>
    </div>
</div>

{{-- KPI strip --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('contracts.analytics_total_all') }}</p>
        <p class="text-[22px] font-bold text-[#00d9b5] leading-none break-all">{{ $kpis['total_all'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('contracts.analytics_this_month') }}</p>
        <p class="text-[22px] font-bold text-primary leading-none break-all">{{ $kpis['this_month'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('contracts.analytics_this_quarter') }}</p>
        <p class="text-[22px] font-bold text-primary leading-none break-all">{{ $kpis['this_qtr'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('contracts.analytics_this_year') }}</p>
        <p class="text-[22px] font-bold text-primary leading-none break-all">{{ $kpis['this_year'] }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- 12-month spend chart (CSS bars — no JS needed) --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.analytics_monthly_chart') }}</h3>
                <p class="text-[12px] text-muted mt-0.5">{{ __('contracts.analytics_monthly_subtitle') }}</p>
            </div>
            @if($kpis['avg_velocity'] !== null)
            <div class="text-end">
                <p class="text-[11px] text-muted uppercase tracking-wider">{{ __('contracts.analytics_avg_velocity') }}</p>
                <p class="text-[18px] font-bold text-accent">{{ $kpis['avg_velocity'] }} <span class="text-[11px] text-muted">{{ __('common.days') }}</span></p>
            </div>
            @endif
        </div>

        <div class="flex items-end gap-2 h-[220px]" role="img" aria-label="{{ __('contracts.analytics_monthly_chart') }}">
            @foreach($monthly as $month)
            @php $heightPct = $monthly_max > 0 ? max(2, round(($month['value'] / $monthly_max) * 100, 1)) : 0; @endphp
            <div class="flex-1 flex flex-col items-center gap-2 min-w-0">
                <div class="w-full flex items-end justify-center" style="height: 180px;">
                    <div class="w-full bg-gradient-to-t from-accent to-[#6b91ff] rounded-t-md transition-all hover:opacity-80"
                         style="height: {{ $heightPct }}%; min-height: 2px;"
                         title="{{ $month['label'] }}: {{ number_format($month['value'], 0) }} AED"></div>
                </div>
                <span class="text-[10px] text-muted truncate">{{ $month['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Status breakdown --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.analytics_status_breakdown') }}</h3>
        @if(empty($status_counts))
            <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
        @else
            @php
                $total = array_sum($status_counts);
                $statusLabels = [
                    'draft'                     => ['label' => __('status.draft'),     'color' => '#b4b6c0'],
                    'pending_internal_approval' => ['label' => 'Internal Approval',    'color' => '#8b5cf6'],
                    'pending_signatures'        => ['label' => __('status.pending'),   'color' => '#ffb020'],
                    'signed'                    => ['label' => __('status.signed') ?? 'Signed', 'color' => '#4f7cff'],
                    'active'                    => ['label' => __('contracts.active'),    'color' => '#3B82F6'],
                    'completed'                 => ['label' => __('contracts.completed'), 'color' => '#00d9b5'],
                    'cancelled'                 => ['label' => __('status.cancelled'),    'color' => '#ef4444'],
                    'terminated'                => ['label' => 'Terminated',              'color' => '#ef4444'],
                ];
            @endphp
            <ul class="space-y-3">
                @foreach($status_counts as $status => $count)
                @php
                    $meta = $statusLabels[$status] ?? ['label' => ucfirst((string) $status), 'color' => '#b4b6c0'];
                    $pct  = $total > 0 ? round(($count / $total) * 100) : 0;
                @endphp
                <li>
                    <div class="flex items-center justify-between text-[12px] mb-1">
                        <span class="text-body">{{ $meta['label'] }}</span>
                        <span class="font-semibold text-primary">{{ $count }}<span class="text-muted text-[11px] ms-1">({{ $pct }}%)</span></span>
                    </div>
                    <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $meta['color'] }};" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- Top suppliers --}}
<div class="mt-6 bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.analytics_top_suppliers') }}</h3>
    @if(empty($top_suppliers))
        <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
    @else
        @php $maxRaw = collect($top_suppliers)->max('raw') ?: 1; @endphp
        <ul class="space-y-4">
            @foreach($top_suppliers as $i => $sup)
            @php $widthPct = round(($sup['raw'] / $maxRaw) * 100); @endphp
            <li>
                <div class="flex items-center justify-between text-[13px] mb-1.5 gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-6 h-6 rounded-full bg-accent/15 text-accent text-[11px] font-bold flex items-center justify-center flex-shrink-0">{{ $i + 1 }}</span>
                        <span class="font-semibold text-primary truncate">{{ $sup['name'] }}</span>
                    </div>
                    <span class="font-bold text-[#00d9b5] flex-shrink-0">{{ $sup['value'] }}</span>
                </div>
                <div class="w-full h-2 bg-elevated rounded-full overflow-hidden ms-8">
                    <div class="h-full bg-gradient-to-r from-accent to-[#00d9b5] rounded-full" style="width: {{ $widthPct }}%;" role="progressbar" aria-valuenow="{{ $widthPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </li>
            @endforeach
        </ul>
    @endif
</div>

@endsection
