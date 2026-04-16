@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.payments_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.payments_title')" :subtitle="__('gov.payments_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total_payments'])" :label="__('gov.total_payments')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['completed'])" :label="__('gov.completed_payments')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_amount'])" :label="__('gov.total_amount')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['total_vat'])" :label="__('gov.vat_collected')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>' />
</div>

{{-- Monthly payment + VAT trend --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.payment_trend') }}</h3>
    <div class="overflow-x-auto">
        <div class="flex items-end gap-2 h-[200px] min-w-[600px]">
            @php $maxAmt = $paymentTrend->max('amount') ?: 1; @endphp
            @foreach($paymentTrend as $m)
                @php $pct = ($m->amount / $maxAmt) * 100; $vatPct = $maxAmt > 0 ? ($m->vat / $maxAmt) * 100 : 0; @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-[9px] text-muted font-mono">{{ number_format($m->amount / 1000) }}k</span>
                    <div class="w-full flex flex-col gap-0.5" style="height: {{ max($pct, 2) }}%">
                        <div class="flex-1 rounded-t-lg bg-accent/60"></div>
                        <div class="rounded-b-sm bg-[#ffb020]/60" style="height: {{ max($vatPct, 1) }}%"></div>
                    </div>
                    <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($m->month . '-01')->format('M') }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="flex items-center gap-4 mt-3 text-[11px] text-muted">
        <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-accent/60"></span> {{ __('gov.payment_amount') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-[#ffb020]/60"></span> {{ __('gov.vat_amount') }}</span>
    </div>
</div>

{{-- Filter + Export --}}
<div class="flex items-center gap-3 mb-4">
    <form method="GET" class="flex items-center gap-2">
        <select name="status" class="bg-page border border-th-border rounded-xl px-3 h-10 text-[13px] text-primary">
            <option value="">{{ __('common.all_statuses') }}</option>
            @foreach(['pending_approval','processing','completed','failed','refunded'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
    </form>
    <a href="{{ route('gov.export', ['type' => 'payments']) }}" class="px-4 h-10 rounded-xl text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        CSV
    </a>
</div>

{{-- Payments table --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <table class="w-full text-[13px]">
        <thead>
            <tr class="border-b border-th-border bg-surface-2">
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">ID</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.amount') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">VAT</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $p)
            @php
                $pColors = ['pending_approval'=>'#ffb020','processing'=>'#4f7cff','completed'=>'#00d9b5','failed'=>'#ff4d7f','refunded'=>'#8B5CF6'];
                $pc = $pColors[$p->status?->value ?? 'pending'] ?? '#525252';
            @endphp
            <tr class="border-b border-th-border/50 hover:bg-surface-2">
                <td class="py-3 px-4 font-mono text-muted">PAY-{{ $p->id }}</td>
                <td class="py-3 px-4 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:{{ $pc }}1A;color:{{ $pc }};">{{ ucfirst(str_replace('_',' ',$p->status?->value ?? '')) }}</span></td>
                <td class="py-3 px-4 text-right font-semibold text-primary">{{ $p->currency ?? 'AED' }} {{ number_format($p->amount, 2) }}</td>
                <td class="py-3 px-4 text-right text-muted">{{ number_format($p->vat_amount, 2) }}</td>
                <td class="py-3 px-4 text-right text-muted">{{ $p->created_at?->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="py-12 text-center text-[14px] text-muted">{{ __('gov.no_payments') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $payments->links() }}</div>

@endsection
