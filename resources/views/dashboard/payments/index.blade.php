@extends('layouts.dashboard', ['active' => 'payments'])
@section('title', __('payments.title'))

@section('content')

<x-dashboard.page-header :title="__('payments.title')" :subtitle="__('payments.subtitle')" />

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['pending']"        :label="__('payments.pending')"         color="orange" />
    <x-dashboard.stat-card :value="$stats['pending_amount']" :label="__('payments.pending_amount')"  color="red" />
    <x-dashboard.stat-card :value="$stats['completed']"      :label="__('payments.completed')"       color="green" />
    <x-dashboard.stat-card :value="$stats['paid_month']"     :label="__('payments.paid_this_month')" color="blue" />
</div>

{{-- Tabs + Search --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
        <div class="flex items-center gap-2 bg-page border border-th-border rounded-full p-1">
            <button class="px-4 py-1.5 rounded-full text-[12px] font-semibold text-muted hover:text-primary">{{ __('status.pending') }} (3)</button>
            <button class="px-4 py-1.5 rounded-full text-[12px] font-semibold text-muted hover:text-primary">{{ __('status.completed') }} (2)</button>
            <button class="px-4 py-1.5 rounded-full text-[12px] font-semibold text-white bg-accent">All (5)</button>
        </div>
        <div class="flex-1 relative">
            <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="{{ __('payments.search_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
        </div>
        <button class="w-10 h-10 rounded-xl bg-page border border-th-border flex items-center justify-center text-muted hover:text-primary"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591"/></svg></button>
        <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg>
            {{ __('common.export') }}
        </button>
    </div>
</div>

{{-- Table --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-page border-b border-th-border">
                <tr>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.payment_id') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.contract_supplier') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('payments.milestone') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.amount') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.due_date') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                    <th class="text-start p-5 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @foreach($payments as $p)
                <tr class="hover:bg-page transition-colors">
                    <td class="p-5">
                        <p class="text-[12px] font-mono font-semibold text-primary">{{ $p['id'] }}</p>
                        <p class="text-[10px] text-muted">{{ $p['method'] }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[12px] font-mono text-accent">{{ $p['contract'] }}</p>
                        <p class="text-[11px] text-muted">{{ $p['supplier'] }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[13px] font-semibold text-primary">{{ $p['milestone'] }} <span class="text-muted font-normal">({{ $p['pct'] }}%)</span></p>
                        <p class="text-[10px] text-muted">of AED {{ number_format($p['of']) }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[16px] font-bold text-accent">{{ $p['amount'] }}</p>
                    </td>
                    <td class="p-5">
                        <p class="text-[12px] text-body">{{ $p['due'] }}</p>
                        @if($p['urgent'])<p class="text-[10px] text-[#EF4444] font-bold">{{ __('status.overdue') }}</p>@endif
                    </td>
                    <td class="p-5">
                        <x-dashboard.status-badge :status="$p['status']" />
                    </td>
                    <td class="p-5">
                        <div class="flex items-center gap-2">
                            @if($p['paid'])
                            <button class="px-4 py-1.5 rounded-lg text-[11px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">{{ __('payments.view_receipt') }}</button>
                            <button class="w-8 h-8 rounded-lg bg-page border border-th-border flex items-center justify-center text-muted hover:text-primary"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg></button>
                            @else
                            <button class="px-4 py-1.5 rounded-lg text-[11px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('payments.pay_now') }}</button>
                            <button class="w-8 h-8 rounded-lg bg-page border border-th-border flex items-center justify-center text-muted hover:text-primary"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg></button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
