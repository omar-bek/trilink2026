@extends('layouts.dashboard', ['active' => 'payments'])
@section('title', __('payments.title'))

@section('content')

<x-dashboard.page-header :title="__('payments.title')" :subtitle="__('payments.subtitle')" />

{{-- Direction filter chips. Mirrors the chips on the contracts listing
     so a tenant playing both buyer and supplier roles can narrow the
     ledger to one cash-flow direction at a time. --}}
@php
    $paymentDirectionChips = [
        ['key' => 'all',      'label' => __('payments.direction_all'),      'icon' => 'M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5'],
        ['key' => 'outgoing', 'label' => __('payments.direction_outgoing'), 'icon' => 'M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18'],
        ['key' => 'incoming', 'label' => __('payments.direction_incoming'), 'icon' => 'M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3'],
    ];
@endphp
<div class="flex items-center gap-2 mb-6 flex-wrap">
    @foreach($paymentDirectionChips as $chip)
        @php
            $isActive = ($directionFilter ?? 'all') === $chip['key'];
            $count    = $directionCounts[$chip['key']] ?? 0;
        @endphp
        <a href="{{ route('dashboard.payments', array_filter([
                'direction' => $chip['key'] === 'all' ? null : $chip['key'],
                'tab'       => $tab !== 'all' ? $tab : null,
                'q'         => $search ?: null,
            ])) }}"
           class="inline-flex items-center gap-2 px-4 h-10 rounded-xl text-[12px] font-semibold border transition-colors
                  {{ $isActive
                      ? 'bg-accent text-white border-accent shadow-[0_4px_14px_rgba(79,124,255,0.25)]'
                      : 'bg-surface text-body border-th-border hover:border-accent/40 hover:text-primary' }}">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chip['icon'] }}"/></svg>
            {{ $chip['label'] }}
            <span class="inline-flex items-center justify-center min-w-[20px] h-[18px] px-1.5 rounded-full text-[10px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-page text-muted border border-th-border' }}">
                {{ $count }}
            </span>
        </a>
    @endforeach
</div>

{{-- Stats — pending/completed cards filter the table; the amount cards are
     informational so they stay non-clickable. --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <x-dashboard.stat-card
        :value="$stats['pending']"
        :label="__('payments.pending')"
        color="orange"
        :href="route('dashboard.payments', array_filter([
            'tab'       => 'pending',
            'direction' => ($directionFilter ?? 'all') !== 'all' ? $directionFilter : null,
            'q'         => $search ?: null,
        ]))"
        :active="$tab === 'pending'" />
    <x-dashboard.stat-card :value="$stats['pending_amount']" :label="__('payments.pending_amount')" color="red" />
    <x-dashboard.stat-card
        :value="$stats['completed']"
        :label="__('payments.completed')"
        color="green"
        :href="route('dashboard.payments', array_filter([
            'tab'       => 'completed',
            'direction' => ($directionFilter ?? 'all') !== 'all' ? $directionFilter : null,
            'q'         => $search ?: null,
        ]))"
        :active="$tab === 'completed'" />
    <x-dashboard.stat-card :value="$stats['paid_month']" :label="__('payments.paid_this_month')" color="blue" />
</div>

{{-- Tabs + Search --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <form method="GET" action="{{ route('dashboard.payments') }}" class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
        {{-- Preserve the active tab + direction while searching. --}}
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if(($directionFilter ?? 'all') !== 'all')
            <input type="hidden" name="direction" value="{{ $directionFilter }}">
        @endif

        <div class="flex items-center gap-2 bg-page border border-th-border rounded-full p-1">
            @php
                $tabs = [
                    'pending'   => __('status.pending'),
                    'completed' => __('status.completed'),
                    'all'       => __('common.all'),
                ];
            @endphp
            @foreach($tabs as $key => $label)
                <a href="{{ route('dashboard.payments', array_filter([
                        'tab'       => $key,
                        'direction' => ($directionFilter ?? 'all') !== 'all' ? $directionFilter : null,
                        'q'         => $search ?: null,
                    ])) }}"
                   class="px-4 py-1.5 rounded-full text-[12px] font-semibold transition-colors {{ $tab === $key ? 'text-white bg-accent' : 'text-muted hover:text-primary' }}">
                    {{ $label }} ({{ $tabCounts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="flex-1 relative">
            <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('payments.search_placeholder') }}"
                   class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
        </div>

        <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            {{ __('common.search') }}
        </button>

        <a href="{{ route('dashboard.payments', array_filter([
                'tab'       => $tab,
                'direction' => ($directionFilter ?? 'all') !== 'all' ? $directionFilter : null,
                'q'         => $search ?: null,
                'export'    => 'csv',
            ])) }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            {{ __('common.export_csv') }}
        </a>
    </form>
</div>

{{-- Table --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-page border-b border-th-border">
                <tr>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.payment_id') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.direction') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.contract_counterparty') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.milestone') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.amount') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.due_date') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($payments as $p)
                @php
                    $showUrl     = route('dashboard.payments.show', ['id' => $p['db_id']]);
                    $isOutgoing  = ($p['direction'] ?? null) === 'outgoing';
                    $dirPill     = $isOutgoing
                        ? ['bg' => 'bg-accent/10',    'text' => 'text-accent',    'border' => 'border-accent/30',    'label' => __('payments.direction_outgoing'), 'icon' => 'M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18']
                        : ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/30', 'label' => __('payments.direction_incoming'), 'icon' => 'M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3'];
                    $cpLabel     = $isOutgoing ? __('payments.to') : __('payments.from');
                @endphp
                <tr class="hover:bg-page transition-colors cursor-pointer" onclick="window.location='{{ $showUrl }}'">
                    <td class="p-5">
                        <p class="text-[12px] font-mono font-semibold text-primary">{{ $p['id'] }}</p>
                        <p class="text-[10px] text-muted">{{ $p['method'] }}</p>
                    </td>
                    <td class="p-5">
                        <span class="inline-flex items-center gap-1 px-2 h-[22px] rounded-full border text-[10px] font-bold uppercase tracking-wider {{ $dirPill['bg'] }} {{ $dirPill['text'] }} {{ $dirPill['border'] }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $dirPill['icon'] }}"/></svg>
                            {{ $dirPill['label'] }}
                        </span>
                    </td>
                    <td class="p-5">
                        <p class="text-[12px] font-mono text-accent">{{ $p['contract'] }}</p>
                        <p class="text-[11px] text-muted">{{ $cpLabel }}: <span class="text-body font-semibold">{{ $p['counterparty'] ?? $p['supplier'] }}</span></p>
                    </td>
                    <td class="p-5">
                        <p class="text-[13px] font-semibold text-primary">{{ $p['milestone'] }} <span class="text-muted font-normal">({{ $p['pct'] }}%)</span></p>
                        <p class="text-[10px] text-muted">{{ __('common.of') }} AED {{ number_format($p['of']) }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[16px] font-bold text-accent">{{ $p['amount'] }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[12px] text-body">{{ $p['due'] }}</p>
                        @if($p['urgent'])<p class="text-[10px] text-[#ff4d7f] font-bold">{{ __('status.overdue') }}</p>@endif
                    </td>
                    <td class="p-5">
                        <x-dashboard.status-badge :status="$p['status']" />
                    </td>
                    <td class="p-5" onclick="event.stopPropagation()">
                        <div class="flex items-center gap-2">
                            @if($p['paid'])
                            <a href="{{ $showUrl }}" class="px-4 py-1.5 rounded-lg text-[11px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">{{ __('payments.view_receipt') }}</a>
                            @else
                            <a href="{{ $showUrl }}" class="px-4 py-1.5 rounded-lg text-[11px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('payments.pay_now') }}</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="p-0">
                        <x-dashboard.empty-state
                            :title="__('payments.empty_title')"
                            :message="$search !== '' || $tab !== 'all' || ($directionFilter ?? 'all') !== 'all' ? __('payments.no_results_message') : __('payments.empty_message')" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
