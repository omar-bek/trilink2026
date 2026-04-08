@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('bids.compare_title'))

@php
// Risk pill colors — keyed by risk tier so the cell stays in sync with
// whatever the controller computed (very_low / low / medium / high).
$riskPill = [
    'very_low' => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/30'],
    'low'      => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/30'],
    'medium'   => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/30'],
    'high'     => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/30'],
];
@endphp

@section('content')

{{-- ───────── Header ───────── --}}
<div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
    <div class="min-w-0">
        <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}"
           class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/>
            </svg>
            {{ __('bids.back_to_rfq') }}
        </a>
        <p class="text-[12px] font-mono text-muted mb-1">#{{ $rfq['id'] }}</p>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight">{{ __('bids.compare_title') }}</h1>
        <p class="text-[14px] text-muted mt-1">{{ __('bids.compare_subtitle') }}</p>
    </div>
    <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-surface border border-th-border text-[13px] font-semibold text-primary hover:bg-surface-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        {{ __('bids.export_comparison') }}
    </button>
</div>

@if(empty($bidColumns))

{{-- Empty state — no live bids to compare yet --}}
<div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
    <p class="text-[14px] text-muted">{{ __('bids.no_bids_to_compare') }}</p>
</div>

@else

{{-- ───────── AI Recommendation ───────── --}}
@if($recommendation)
<div class="mb-5 bg-accent/5 border border-accent/30 rounded-2xl p-5">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-accent/15 border border-accent/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-[16px] font-bold text-primary mb-1">{{ __('bids.ai_recommendation') }}</h3>
            <p class="text-[13px] text-muted leading-relaxed">
                {!! __('bids.ai_recommendation_text') . ' <span class="text-accent font-semibold">' . e($recommendation['name']) . '</span> ' . __('bids.ai_recommendation_for_rfq') !!}
            </p>
            <div class="flex items-center flex-wrap gap-x-5 gap-y-2 mt-3">
                @if($recommendation['best_value'])
                <span class="inline-flex items-center gap-1.5 text-[12px] text-[#00d9b5] font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('bids.best_value') }}
                </span>
                @endif
                @if($recommendation['highest_compliance'])
                <span class="inline-flex items-center gap-1.5 text-[12px] text-[#00d9b5] font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('bids.highest_compliance') }}
                </span>
                @endif
                @if($recommendation['low_risk'])
                <span class="inline-flex items-center gap-1.5 text-[12px] text-[#00d9b5] font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('bids.low_risk') }}
                </span>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- ───────── Privacy Protected Bidding ───────── --}}
<div class="mb-6 bg-[#8b5cf6]/5 border border-[#8b5cf6]/30 rounded-2xl p-5">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-[#8b5cf6]/15 border border-[#8b5cf6]/30 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-[15px] font-bold text-[#8b5cf6] mb-1">{{ __('bids.privacy_protected') }}</h3>
            <p class="text-[13px] text-muted leading-relaxed">{{ __('bids.privacy_body') }}</p>
        </div>
    </div>
</div>

{{-- ───────── Stat Cards ───────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[12px] text-muted mb-1">{{ __('bids.total_bids') }}</p>
        <p class="text-[28px] font-bold text-primary leading-none">{{ $stats['total_bids'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[12px] text-muted mb-1">{{ __('bids.price_range') }}</p>
        <p class="text-[22px] font-bold text-[#00d9b5] leading-none">{{ $stats['price_range'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[12px] text-muted mb-1">{{ __('bids.avg_timeline') }}</p>
        <p class="text-[22px] font-bold text-primary leading-none">{{ $stats['avg_timeline'] }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[12px] text-muted mb-1">{{ __('bids.avg_rating') }}</p>
        <p class="text-[22px] font-bold text-primary leading-none flex items-center gap-1.5">
            <svg class="w-5 h-5 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
            {{ $stats['avg_rating'] }}
        </p>
    </div>
</div>

{{-- ───────── Comparison Table ───────── --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-page border-b border-th-border">
                    <th class="text-start p-5 text-[12px] font-semibold text-muted uppercase tracking-wider align-top w-[200px]">
                        {{ __('bids.criteria') }}
                    </th>
                    @foreach($bidColumns as $col)
                    <th class="text-center p-5 align-top border-s border-th-border min-w-[200px]">
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-[14px] font-bold">
                                {{ $col['short_code'] }}
                            </div>
                            <p class="text-[14px] font-bold text-primary">{{ $col['name'] }}</p>
                            @if($col['is_recommended'])
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-accent text-white text-[11px] font-semibold">
                                {{ __('bids.recommended') }}
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-page border border-th-border text-muted text-[11px] font-medium">
                                {{ __('bids.select') }}
                            </span>
                            @endif
                        </div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">

                {{-- Total Price --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.total_price') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="text-[20px] font-bold text-[#00d9b5]">{{ $col['price'] }}</p>
                    </td>
                    @endforeach
                </tr>

                {{-- Delivery Timeline --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.delivery_timeline') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-primary">
                            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $col['days'] }} {{ __('common.days') }}
                        </p>
                    </td>
                    @endforeach
                </tr>

                {{-- Supplier Rating --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.supplier_rating') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-primary">
                            <svg class="w-4 h-4 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                            {{ $col['rating'] }}
                            <span class="text-muted font-normal">({{ $col['rating_count'] }})</span>
                        </p>
                    </td>
                    @endforeach
                </tr>

                {{-- Compliance Score --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.compliance_score') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 border-s border-th-border">
                        @if($col['compliance'] !== null)
                        <p class="text-center text-[13px] font-semibold text-primary mb-1.5">{{ $col['compliance'] }}%</p>
                        <div class="w-full h-1.5 rounded-full bg-page overflow-hidden">
                            <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $col['compliance'] }}%"></div>
                        </div>
                        @else
                        <p class="text-center text-[13px] text-muted">—</p>
                        @endif
                    </td>
                    @endforeach
                </tr>

                {{-- Risk Score --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.risk_score') }}</td>
                    @foreach($bidColumns as $col)
                    @php $rp = $riskPill[$col['risk']['key']] ?? $riskPill['medium']; @endphp
                    <td class="p-5 text-center border-s border-th-border">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-semibold border {{ $rp['bg'] }} {{ $rp['text'] }} {{ $rp['border'] }}">
                            {{ $col['risk']['label'] }}
                        </span>
                    </td>
                    @endforeach
                </tr>

                {{-- Certifications --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.certifications') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 border-s border-th-border">
                        <div class="flex items-center justify-center flex-wrap gap-1.5">
                            @forelse($col['certifications'] as $cert)
                            <span class="inline-flex items-center px-2 py-1 rounded-md bg-page border border-th-border text-[10px] font-medium text-muted">
                                {{ $cert }}
                            </span>
                            @empty
                            <span class="text-[12px] text-muted">—</span>
                            @endforelse
                        </div>
                    </td>
                    @endforeach
                </tr>

                {{-- Payment Terms --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.payment_terms') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="text-[13px] font-semibold text-primary">{{ $col['payment_terms'] }}</p>
                    </td>
                    @endforeach
                </tr>

                {{-- Warranty --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.warranty') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="text-[13px] font-semibold text-primary">{{ $col['warranty'] }}</p>
                    </td>
                    @endforeach
                </tr>

                {{-- Completion Rate --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.completion_rate') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="text-[14px] font-bold text-primary">{{ $col['completion_rate'] }}%</p>
                    </td>
                    @endforeach
                </tr>

                {{-- On-Time Delivery --}}
                <tr class="hover:bg-page/40 transition-colors">
                    <td class="p-5 text-[13px] font-semibold text-primary">{{ __('bids.on_time_delivery') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 text-center border-s border-th-border">
                        <p class="text-[14px] font-bold text-primary">{{ $col['on_time_rate'] }}%</p>
                    </td>
                    @endforeach
                </tr>

                {{-- Actions --}}
                <tr>
                    <td class="p-5 text-[13px] font-semibold text-primary align-top">{{ __('common.actions') }}</td>
                    @foreach($bidColumns as $col)
                    <td class="p-5 border-s border-th-border align-top">
                        <div class="flex flex-col gap-2">
                            <a href="{{ route('dashboard.bids.show', ['id' => $col['id']]) }}"
                               class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-page border border-th-border text-[12px] font-semibold text-primary hover:bg-surface-2 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ __('bids.view_details') }}
                            </a>
                            @if($col['can_accept'])
                            <form method="POST" action="{{ route('dashboard.bids.accept', ['id' => $col['id']]) }}"
                                  onsubmit="return confirm('{{ __('bids.confirm_select') }}');">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-accent text-white text-[12px] font-semibold hover:bg-accent/90 transition-colors">
                                    {{ __('bids.select_continue') }}
                                </button>
                            </form>
                            @else
                            <button type="button" disabled
                                    class="w-full inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-page border border-th-border text-[12px] font-semibold text-muted cursor-not-allowed">
                                {{ __('bids.unavailable') }}
                            </button>
                            @endif
                        </div>
                    </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endif

@endsection
