@extends('layouts.dashboard', ['active' => 'admin-reports'])
@section('title', __('admin.reports.title'))

@section('content')

<x-admin.navbar active="reports" />

<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div>
        <h2 class="text-[20px] font-bold text-primary">{{ __('admin.reports.title') }}</h2>
        <p class="text-[13px] text-muted mt-1">{{ __('admin.reports.subtitle') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <select name="range" class="bg-page border border-th-border rounded-xl px-3 h-10 text-[13px] text-primary">
                @foreach([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $v => $l)
                    <option value="{{ $v }}" @selected($range == $v)>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
        </form>
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="px-4 h-10 rounded-xl text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2">
                {{ __('common.export') }} CSV
            </button>
            <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-1 w-48 bg-surface border border-th-border rounded-xl shadow-lg z-10 py-1">
                <a href="{{ route('admin.reports.export', ['type' => 'suppliers']) }}" class="block px-4 py-2 text-[13px] text-primary hover:bg-surface-2">{{ __('admin.reports.export_suppliers') }}</a>
                <a href="{{ route('admin.reports.export', ['type' => 'contracts']) }}" class="block px-4 py-2 text-[13px] text-primary hover:bg-surface-2">{{ __('admin.reports.export_contracts') }}</a>
                <a href="{{ route('admin.reports.export', ['type' => 'payments']) }}" class="block px-4 py-2 text-[13px] text-primary hover:bg-surface-2">{{ __('admin.reports.export_payments') }}</a>
            </div>
        </div>
    </div>
</div>

{{-- Platform KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_gmv'])" :label="__('gov.active_gmv')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22M21 8.689V5.25H17.561"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_payments'])" :label="__('admin.reports.total_payments')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_vat'])" :label="__('gov.vat_collected')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['avg_cycle_days'], 1) . ' days'" :label="__('admin.reports.avg_cycle')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
</div>

{{-- Monthly Contract Trend Chart --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.reports.monthly_trend') }}</h3>
    <div class="overflow-x-auto">
        <div class="flex items-end gap-2 h-[200px] min-w-[600px]">
            @php $maxVal = $monthlyTrend->max('value') ?: 1; @endphp
            @foreach($monthlyTrend as $m)
                @php $pct = ($m->value / $maxVal) * 100; @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-[10px] text-muted font-mono">AED {{ number_format($m->value / 1000) }}k</span>
                    <div class="w-full rounded-t-lg bg-accent/20 relative" style="height: {{ max($pct, 2) }}%">
                        <div class="absolute inset-0 rounded-t-lg bg-accent" style="opacity: 0.7"></div>
                    </div>
                    <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month . '-01')->format('M') }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Savings Report --}}
@if($savingsData && $savingsData->count > 0)
<div class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.reports.savings') }}</h3>
    <div class="grid grid-cols-3 gap-4">
        <div class="text-center p-4 bg-page rounded-xl">
            <p class="text-[24px] font-bold text-primary">AED {{ number_format($savingsData->total_budget) }}</p>
            <p class="text-[12px] text-muted mt-1">{{ __('admin.reports.total_budget') }}</p>
        </div>
        <div class="text-center p-4 bg-page rounded-xl">
            <p class="text-[24px] font-bold text-[#00d9b5]">AED {{ number_format($savingsData->total_awarded) }}</p>
            <p class="text-[12px] text-muted mt-1">{{ __('admin.reports.total_awarded') }}</p>
        </div>
        <div class="text-center p-4 bg-page rounded-xl">
            @php $saved = $savingsData->total_budget - $savingsData->total_awarded; $pct = $savingsData->total_budget > 0 ? round(($saved / $savingsData->total_budget) * 100, 1) : 0; @endphp
            <p class="text-[24px] font-bold {{ $saved > 0 ? 'text-[#00d9b5]' : 'text-[#ff4d7f]' }}">{{ $pct }}%</p>
            <p class="text-[12px] text-muted mt-1">{{ __('admin.reports.savings_pct') }}</p>
        </div>
    </div>
</div>
@endif

{{-- Supplier Scorecard --}}
<div class="bg-surface border border-th-border rounded-2xl p-5">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.reports.supplier_scorecard') }}</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead>
                <tr class="border-b border-th-border">
                    <th class="text-left py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.supplier') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.verification') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.total_bids') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.won_bids') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.win_rate') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.contracts') }}</th>
                    <th class="text-center py-3 px-2 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.reports.disputes') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($supplierScorecard as $s)
                <tr class="border-b border-th-border/50 hover:bg-surface-2 transition-colors">
                    <td class="py-3 px-2 font-semibold text-primary">{{ $s->name }}</td>
                    <td class="py-3 px-2 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent/10 text-accent">{{ $s->verification_level }}</span></td>
                    <td class="py-3 px-2 text-center text-muted">{{ $s->total_bids }}</td>
                    <td class="py-3 px-2 text-center text-[#00d9b5] font-semibold">{{ $s->won_bids }}</td>
                    <td class="py-3 px-2 text-center font-semibold {{ $s->total_bids > 0 ? 'text-primary' : 'text-muted' }}">{{ $s->total_bids > 0 ? round(($s->won_bids / $s->total_bids) * 100) . '%' : '—' }}</td>
                    <td class="py-3 px-2 text-center text-muted">{{ $s->total_contracts }}</td>
                    <td class="py-3 px-2 text-center {{ $s->disputes_against > 0 ? 'text-[#ff4d7f] font-semibold' : 'text-muted' }}">{{ $s->disputes_against }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
