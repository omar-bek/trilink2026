@extends('layouts.dashboard', ['active' => 'escrow'])
@section('title', __('escrow.dashboard_title'))

@section('content')

{{--
    Phase 3 / Sprint 14 / task 3.16 — escrow dashboard.

    Buyers see contracts where they're the buyer; suppliers see contracts
    where they're a party (read-only). The KPI strip is the elevator
    pitch — held vs released across all of the user's escrow accounts —
    and the table drills into each account's per-contract breakdown.
--}}
<div class="mb-6 sm:mb-8 flex items-start gap-4">
    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0 text-accent">
        <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="min-w-0">
        <h1 class="text-[24px] sm:text-[32px] lg:text-[36px] font-bold text-primary leading-tight">{{ __('escrow.dashboard_title') }}</h1>
        <p class="text-[13px] sm:text-[14px] text-muted mt-1">{{ __('escrow.dashboard_subtitle') }}</p>
    </div>
</div>

@php
    $escrowKpis = [
        ['label' => __('escrow.kpi_held'),     'value' => number_format($kpis['total_held'], 2),     'color' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/30', 'bg' => 'bg-[#00d9b5]/[0.05]', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'],
        ['label' => __('escrow.kpi_released'), 'value' => number_format($kpis['total_released'], 2), 'color' => 'text-primary',   'border' => 'border-th-border',     'bg' => 'bg-surface',          'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['label' => __('escrow.kpi_active'),   'value' => $kpis['active_count'],                     'color' => 'text-accent',     'border' => 'border-accent/30',     'bg' => 'bg-accent/[0.05]',    'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => __('escrow.kpi_closed'),   'value' => $kpis['closed_count'],                     'color' => 'text-muted',      'border' => 'border-th-border',     'bg' => 'bg-surface',          'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @foreach($escrowKpis as $k)
    <div class="border-2 {{ $k['border'] }} {{ $k['bg'] }} rounded-2xl p-5 transition-transform hover:-translate-y-0.5">
        <div class="flex items-start justify-between mb-3">
            <p class="text-[24px] font-bold {{ $k['color'] }} leading-tight">{{ $k['value'] }}</p>
            <div class="w-9 h-9 rounded-lg {{ $k['bg'] }} border {{ $k['border'] }} flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] {{ $k['color'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $k['icon'] }}"/></svg>
            </div>
        </div>
        <p class="text-[12px] text-muted">{{ $k['label'] }}</p>
    </div>
    @endforeach
</div>

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="px-6 py-4 border-b border-th-border flex items-center justify-between">
        <h3 class="text-[15px] font-bold text-primary">{{ __('escrow.accounts_table') }}</h3>
        <p class="text-[12px] text-muted">{{ count($accounts) }} {{ __('escrow.accounts') }}</p>
    </div>

    @if($accounts->isEmpty())
    <div class="p-10 sm:p-14 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-4 text-accent">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-[15px] font-bold text-primary">{{ __('escrow.empty_title') }}</p>
        <p class="text-[12.5px] text-muted mt-1 max-w-[400px] mx-auto">{{ __('escrow.empty_subtitle') }}</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead class="bg-page text-muted uppercase tracking-wide text-[10px]">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('escrow.col_contract') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('escrow.col_status') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('escrow.col_deposited') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('escrow.col_released') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('escrow.col_available') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('escrow.col_partner') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @foreach($accounts as $account)
                <tr class="hover:bg-page transition-colors">
                    <td class="px-4 py-4">
                        <p class="font-mono text-[11px] text-muted">{{ $account->contract?->contract_number }}</p>
                        <p class="font-semibold text-primary truncate max-w-[260px]">{{ $account->contract?->title }}</p>
                    </td>
                    <td class="px-4 py-4">
                        <span @class([
                            'inline-flex text-[10px] font-bold rounded-full px-2 py-0.5 border',
                            'text-[#00d9b5] bg-[#00d9b5]/10 border-[#00d9b5]/20' => $account->status === 'active',
                            'text-muted bg-surface-2 border-th-border'           => $account->status === 'pending',
                            'text-[#ffb020] bg-[#ffb020]/10 border-[#ffb020]/20' => $account->status === 'closed',
                            'text-[#f59e0b] bg-[#f59e0b]/10 border-[#f59e0b]/20' => $account->status === 'refunded',
                        ])>{{ __('escrow.status_' . $account->status) }}</span>
                    </td>
                    <td class="px-4 py-4 text-end font-semibold">{{ $account->currency }} {{ number_format((float) $account->total_deposited, 2) }}</td>
                    <td class="px-4 py-4 text-end font-semibold">{{ $account->currency }} {{ number_format((float) $account->total_released, 2) }}</td>
                    <td class="px-4 py-4 text-end font-semibold text-[#00d9b5]">{{ $account->currency }} {{ number_format($account->availableBalance(), 2) }}</td>
                    <td class="px-4 py-4 text-muted">{{ $account->bank_partner }}</td>
                    <td class="px-4 py-4 text-end">
                        <a href="{{ route('dashboard.contracts.show', ['id' => $account->contract_id]) }}"
                           class="text-accent hover:underline text-[11px] font-semibold">{{ __('common.view') }} →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
