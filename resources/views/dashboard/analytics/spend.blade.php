@extends('layouts.dashboard', ['active' => 'analytics'])
@section('title', __('analytics.spend_title'))

@section('content')

<x-dashboard.page-header :title="__('analytics.spend_title')" :subtitle="__('analytics.spend_subtitle')" />

@php
$fmt = fn ($v) => number_format((float) $v, 0);
@endphp

{{-- KPI cards — promoted to the shared stat-card component so the spend
     dashboard reads with the same colored borders + icons as every other
     dashboard. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <x-dashboard.stat-card
        :value="$fmt($summary['total_spend'])"
        :label="__('analytics.total_spend')"
        color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22M21 8.689V5.25H17.561"/>' />
    <x-dashboard.stat-card
        :value="$fmt($summary['spend_last_30_days'])"
        :label="__('analytics.last_30_days')"
        color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5"/>' />
    <x-dashboard.stat-card
        :value="$fmt($summary['contract_count'])"
        :label="__('analytics.contracts')"
        color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M9 15l2 2 4-4"/>' />
    <x-dashboard.stat-card
        :value="$fmt($summary['spend_last_365_days'])"
        :label="__('analytics.last_year')"
        color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>' />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Monthly trend --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-[16px] p-5 sm:p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-[12px] bg-accent-info/10 border border-accent-info/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-accent-info" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
            </div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('analytics.monthly_trend') }}</h3>
        </div>
        @php
        $maxTrend = max(array_column($monthlyTrend, 'total')) ?: 1;
        @endphp
        <div class="flex items-end gap-2 h-48">
            @foreach($monthlyTrend as $m)
                @php
                $h = $maxTrend > 0 ? max(2, ($m['total'] / $maxTrend) * 100) : 2;
                @endphp
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="text-[10px] text-muted">{{ $fmt($m['total']) }}</div>
                    <div class="w-full bg-accent/20 hover:bg-accent/40 rounded-t transition-colors" style="height: {{ $h }}%" title="{{ $m['label'] }}: {{ $fmt($m['total']) }}"></div>
                    <div class="text-[10px] text-muted">{{ substr($m['label'], 0, 3) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top suppliers --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-[12px] bg-accent-success/10 border border-accent-success/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-accent-success" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg>
            </div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('analytics.top_suppliers') }}</h3>
        </div>
        <div class="space-y-4">
        @forelse($topSuppliers as $i => $s)
            @php
            $share = $summary['total_spend'] > 0 ? ($s['total'] / $summary['total_spend']) * 100 : 0;
            $rankColors = ['#ffb020', '#b4b6c0', '#cd7f32']; // gold, silver, bronze
            $rankColor = $rankColors[$i] ?? '#4f7cff';
            @endphp
            <div>
                <div class="flex items-center justify-between text-[12px] mb-1.5">
                    <span class="inline-flex items-center gap-2 font-semibold text-primary truncate min-w-0">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0" style="background: {{ $rankColor }}1a; color: {{ $rankColor }}; border: 1px solid {{ $rankColor }}40;">{{ $i + 1 }}</span>
                        <span class="truncate">{{ $s['name'] }}</span>
                    </span>
                    <span class="text-muted whitespace-nowrap font-mono">{{ $fmt($s['total']) }}</span>
                </div>
                <div class="h-2 rounded-full bg-surface-2 overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ min(100, $share) }}%; background: {{ $rankColor }};"></div>
                </div>
                <div class="text-[10px] text-muted mt-1">{{ $s['count'] }} {{ __('analytics.contracts_lower') }} · {{ number_format($share, 1) }}%</div>
            </div>
        @empty
            <div class="text-center py-8">
                <div class="mx-auto w-12 h-12 rounded-full bg-accent-success/10 border border-accent-success/20 flex items-center justify-center mb-2">
                    <svg class="w-6 h-6 text-accent-success" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9"/></svg>
                </div>
                <p class="text-[12px] text-muted">{{ __('analytics.no_data') }}</p>
            </div>
        @endforelse
        </div>
    </div>
</div>

{{-- Spend by category --}}
<div class="mt-6 bg-surface border border-th-border rounded-[16px] p-[25px]">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-[12px] bg-accent-violet/10 border border-accent-violet/20 flex items-center justify-center flex-shrink-0">
            <svg class="w-[18px] h-[18px] text-accent-violet" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
        </div>
        <h3 class="text-[15px] font-bold text-primary">{{ __('analytics.spend_by_category') }}</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="border-b border-th-border">
                <tr class="text-[11px] text-muted uppercase tracking-wider">
                    <th class="text-start py-2">{{ __('analytics.category') }}</th>
                    <th class="text-end py-2">{{ __('analytics.contracts') }}</th>
                    <th class="text-end py-2">{{ __('analytics.spend') }}</th>
                    <th class="text-end py-2">{{ __('analytics.share') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($byCategory as $c)
                @php
                $share = $summary['total_spend'] > 0 ? ($c['total'] / $summary['total_spend']) * 100 : 0;
                @endphp
                <tr class="border-b border-th-border/50">
                    <td class="py-3 font-semibold text-primary">{{ $c['category'] }}</td>
                    <td class="py-3 text-end text-muted">{{ $c['count'] }}</td>
                    <td class="py-3 text-end text-primary font-semibold">{{ $fmt($c['total']) }}</td>
                    <td class="py-3 text-end text-muted">{{ number_format($share, 1) }}%</td>
                </tr>
                @empty
                <tr><td colspan="4" class="py-6 text-center text-muted">{{ __('analytics.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
