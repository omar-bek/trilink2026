@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.commodities_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.commodities_title')" :subtitle="__('gov.commodities_subtitle')" :back="route('gov.index')" />

{{-- Window selector + export --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" action="{{ route('gov.commodities-report') }}" class="flex items-center gap-2">
        <label class="text-[12px] text-muted">{{ __('gov.window') }}</label>
        <select name="months" onchange="this.form.submit()"
                class="h-9 px-3 rounded-lg text-[12px] font-semibold bg-surface border border-th-border text-primary">
            @foreach([3, 6, 12, 24, 36] as $w)
                <option value="{{ $w }}" @selected($months === $w)>{{ __('gov.last_n_months', ['n' => $w]) }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('gov.export', ['type' => 'commodities']) }}"
       class="px-3 h-9 rounded-lg text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        {{ __('common.export_csv') }}
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['active_commodities'])" :label="__('gov.active_commodities')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25"/>' />
    <x-dashboard.stat-card :value="number_format($stats['total_rfqs'])" :label="__('gov.rfqs_in_window')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_rfq_budget'])" :label="__('gov.total_rfq_budget')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4 0-1.172.44-2.243 1.172-3.121"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_contract_value'])" :label="__('gov.awarded_value')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' />
</div>

{{-- Top commodities table --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-[15px] font-bold text-primary">{{ __('gov.top_commodities') }}</h3>
        <span class="text-[11px] text-muted">{{ __('gov.sorted_by_rfq_count') }}</span>
    </div>

    @if($topCommodities->isEmpty())
        <p class="text-[13px] text-muted text-center py-8">{{ __('gov.no_commodity_activity') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left border-b border-th-border">
                        <th class="pb-3 font-semibold text-muted">{{ __('gov.commodity') }}</th>
                        <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.rfqs') }}</th>
                        <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.avg_bids') }}</th>
                        <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.contracts') }}</th>
                        <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.contract_value') }}</th>
                        <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.share') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topCommodities as $row)
                        @php
                            $share = $totalCategoryValue > 0
                                ? round(($row->contract_value_total / $totalCategoryValue) * 100, 1)
                                : 0;
                        @endphp
                        <tr class="border-b border-th-border/50 hover:bg-page/50">
                            <td class="py-3">
                                <p class="font-semibold text-primary">{{ $row->name }}</p>
                            </td>
                            <td class="py-3 text-right font-semibold text-primary">{{ number_format($row->rfq_count) }}</td>
                            <td class="py-3 text-right">
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $row->avg_bids < 2 ? 'bg-[#ff4d7f]/10 text-[#ff4d7f]' : ($row->avg_bids < 3 ? 'bg-[#ffb020]/10 text-[#ffb020]' : 'bg-[#00d9b5]/10 text-[#00d9b5]') }}">
                                    {{ $row->avg_bids }}
                                </span>
                            </td>
                            <td class="py-3 text-right text-primary">{{ number_format($row->contract_count) }}</td>
                            <td class="py-3 text-right font-bold text-primary">AED {{ number_format($row->contract_value_total) }}</td>
                            <td class="py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-20 h-1.5 bg-page rounded-full overflow-hidden">
                                        <div class="h-full bg-accent rounded-full" style="width: {{ min($share, 100) }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-semibold text-muted min-w-[36px] text-right">{{ $share }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Monthly RFQ trend --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.monthly_rfq_trend') }}</h3>
    @if($monthlyTrend->isEmpty())
        <p class="text-[13px] text-muted text-center py-8">{{ __('gov.no_trend_data') }}</p>
    @else
        <div class="overflow-x-auto">
            <div class="flex items-end gap-2 h-[180px] min-w-[500px]">
                @php $maxRfq = $monthlyTrend->max('rfq_count') ?: 1; @endphp
                @foreach($monthlyTrend as $m)
                    @php $pct = ($m->rfq_count / $maxRfq) * 100; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-[10px] text-muted font-mono">{{ $m->rfq_count }}</span>
                        <div class="w-full rounded-t-lg bg-accent/60" style="height: {{ max($pct, 2) }}%"
                             title="AED {{ number_format($m->budget_total) }}"></div>
                        <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month.'-01')->format('M y') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Hot catalogue products --}}
@if($topProducts->isNotEmpty())
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.hot_products') }}</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead>
                <tr class="text-left border-b border-th-border">
                    <th class="pb-3 font-semibold text-muted">{{ __('gov.product') }}</th>
                    <th class="pb-3 font-semibold text-muted">{{ __('gov.commodity') }}</th>
                    <th class="pb-3 font-semibold text-muted">{{ __('gov.supplier') }}</th>
                    <th class="pb-3 font-semibold text-muted">{{ __('gov.hs_code') }}</th>
                    <th class="pb-3 font-semibold text-muted text-right">{{ __('gov.price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topProducts as $p)
                    <tr class="border-b border-th-border/50">
                        <td class="py-3 font-semibold text-primary">{{ $p->name }}</td>
                        <td class="py-3 text-muted">{{ $p->category_name ?? '—' }}</td>
                        <td class="py-3 text-muted">{{ $p->supplier_name ?? '—' }}</td>
                        <td class="py-3 text-muted font-mono text-[11px]">{{ $p->hs_code ?? '—' }}</td>
                        <td class="py-3 text-right font-bold text-primary">
                            {{ $p->currency }} {{ number_format((float) $p->base_price, 2) }}
                            <span class="text-[11px] font-normal text-muted">/ {{ $p->unit }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
