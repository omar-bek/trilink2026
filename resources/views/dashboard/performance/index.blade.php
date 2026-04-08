@extends('layouts.dashboard', ['active' => 'performance'])
@section('title', __('performance.title'))

@section('content')

<x-dashboard.page-header :title="__('performance.title')" :subtitle="__('performance.subtitle')" />

@php
// Labels swap based on whether the viewer is a buyer or supplier — same KPI
// slots, different framing of the same data.
$isSupplier = ($role ?? 'buyer') === 'supplier';
$labels = $isSupplier
    ? [
        'kpi1' => __('performance.total_bids'),
        'kpi2' => __('performance.bids_won'),
        'kpi3' => __('performance.total_revenue'),
        'kpi3_short' => __('performance.this_month'),
    ]
    : [
        'kpi1' => __('performance.purchase_requests'),
        'kpi2' => __('performance.rfqs_published'),
        'kpi3' => __('performance.total_spend'),
        'kpi3_short' => __('performance.this_month'),
    ];

// Helper: render a real growth badge or null. The badge color flips with sign.
$renderGrowth = function (?int $pct, string $label) {
    if ($pct === null) return null;
    $color = $pct >= 0 ? '#00d9b5' : '#ff4d7f';
    $sign  = $pct >= 0 ? '+' : '';
    return ['text' => "{$sign}{$pct}% {$label}", 'color' => $color];
};

$bidsGrowthBadge = $renderGrowth($stats['bids_growth'] ?? null, __('performance.this_month'));
$revGrowthBadge  = $renderGrowth($stats['revenue_growth'] ?? null, __('performance.this_month'));
@endphp

{{-- Top stats — single rendering loop instead of 4 copy-paste cards. --}}
@php
$kpiCards = [
    [
        'icon'   => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'color'  => '#4f7cff',
        'value'  => $stats['total_bids'],
        'label'  => $labels['kpi1'],
        'foot'   => $bidsGrowthBadge,
    ],
    [
        'icon'   => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'color'  => '#00d9b5',
        'value'  => $stats['bids_won'],
        'label'  => $labels['kpi2'],
        'foot'   => $isSupplier ? ['text' => __('performance.win_rate') . ': ' . $stats['win_rate'] . '%', 'color' => '#00d9b5'] : null,
    ],
    [
        'icon'   => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453.415 2.18.654A60.145 60.145 0 0118 30l-2.74 1.22m0 0l-5.94-2.28m5.94 2.28l-2.28-5.94',
        'color'  => '#ffb020',
        'value'  => $stats['total_revenue'],
        'label'  => $labels['kpi3'],
        'foot'   => $revGrowthBadge,
    ],
    [
        'icon'   => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z',
        'color'  => '#8B5CF6',
        'value'  => $stats['avg_rating'] !== null ? number_format($stats['avg_rating'], 1) : '—',
        'label'  => __('performance.avg_rating'),
        'foot'   => $stats['avg_rating'] !== null && ($stats['rating_count'] ?? 0) > 0
            ? ['text' => trans_choice('performance.rating_count', $stats['rating_count'] ?? 0, ['count' => $stats['rating_count'] ?? 0]), 'color' => '#b4b6c0']
            : ['text' => __('performance.no_ratings_yet'), 'color' => '#b4b6c0'],
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    @foreach($kpiCards as $card)
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                 style="background: {{ $card['color'] }}19; border: 1px solid {{ $card['color'] }}33;">
                <svg class="w-5 h-5" style="color: {{ $card['color'] }};" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                </svg>
            </div>
            @if(!empty($card['foot']) && ($stats['bids_growth'] ?? null) !== null)
                {{-- Trend arrow only when we have a real growth signal. --}}
                <svg class="w-4 h-4" style="color: {{ $card['foot']['color'] }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/>
                </svg>
            @endif
        </div>
        <p class="text-[32px] font-bold text-primary leading-none truncate">{{ $card['value'] }}</p>
        <p class="text-[13px] text-muted mt-2">{{ $card['label'] }}</p>
        @if(!empty($card['foot']))
            <p class="text-[11px] mt-1 truncate" style="color: {{ $card['foot']['color'] }};">{{ $card['foot']['text'] }}</p>
        @endif
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Monthly performance --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-[12px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
            </div>
            <h3 class="text-[16px] font-bold text-primary">{{ __('performance.monthly') }}</h3>
        </div>
        <div class="space-y-4">
            @forelse($monthly as $m)
            <div class="bg-page border border-th-border rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[14px] font-bold text-primary">{{ $m['label'] }}</p>
                    <p class="text-[14px] font-bold text-[#00d9b5]">{{ $m['revenue'] }}</p>
                </div>
                <div class="grid grid-cols-3 gap-4 mb-3">
                    <div>
                        <p class="text-[11px] text-muted">{{ __('supplier.bids_submitted') }}</p>
                        <p class="text-[16px] font-bold text-primary">{{ $m['submitted'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-muted">{{ __('performance.bids_won') }}</p>
                        <p class="text-[16px] font-bold text-[#00d9b5]">{{ $m['won'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-muted">{{ __('performance.win_rate') }}</p>
                        <p class="text-[16px] font-bold text-primary">{{ $m['win_rate'] }}%</p>
                    </div>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#00d9b5] to-[#4f7cff] rounded-full" style="width: {{ $m['win_rate'] }}%"></div>
                </div>
            </div>
            @empty
            <div class="text-center py-10">
                <div class="mx-auto w-12 h-12 rounded-full bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z"/></svg>
                </div>
                <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Quality metrics --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-[12px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
            </div>
            <h3 class="text-[16px] font-bold text-primary">{{ __('performance.quality') }}</h3>
        </div>
        <div class="space-y-5">
            @if($quality['on_time'] !== null)
            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[13px] text-muted">{{ __('performance.on_time') }}</p>
                    <p class="text-[16px] font-bold text-primary">{{ $quality['on_time'] }}%</p>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $quality['on_time'] }}%"></div>
                </div>
            </div>
            @endif

            @if($quality['customer_satisfaction'] !== null)
            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[13px] text-muted">{{ __('performance.customer_satisfaction') }}</p>
                    <p class="text-[16px] font-bold text-primary">{{ number_format($quality['customer_satisfaction'], 1) }}/5.0</p>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-[#ffb020] rounded-full" style="width: {{ ($quality['customer_satisfaction'] / 5) * 100 }}%"></div>
                </div>
                @if(($quality['satisfaction_count'] ?? 0) > 0)
                <p class="text-[10px] text-faint mt-1">
                    {{ trans_choice('performance.based_on_reviews', $quality['satisfaction_count'], ['count' => $quality['satisfaction_count']]) }}
                </p>
                @endif
            </div>
            @endif

            @if($quality['on_time'] === null && $quality['customer_satisfaction'] === null)
            <div class="text-center py-10">
                <div class="mx-auto w-12 h-12 rounded-full bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                </div>
                <p class="text-[13px] text-muted">{{ __('performance.no_quality_data') }}</p>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
