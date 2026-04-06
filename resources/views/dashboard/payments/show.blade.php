@extends('layouts.dashboard', ['active' => 'payments'])
@section('title', __('payments.details'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <a href="{{ route('dashboard.payments') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <p class="text-[12px] font-mono text-muted mb-2">{{ $payment['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary leading-tight">{{ $payment['milestone'] }}</h1>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <x-dashboard.status-badge :status="$payment['status']" />
            <span class="text-[13px] text-muted">{{ $payment['method'] }}</span>
            <span class="text-faint">·</span>
            <span class="text-[13px] text-muted">{{ __('common.created') }}: {{ $payment['created'] }}</span>
        </div>
    </div>
    @if(!$payment['paid'])
    <div class="flex items-center gap-3">
        @php $paymentNumericId = preg_replace('/PAY-2024-(\d+)/', '$1', $payment['id']); @endphp
        @can('payment.approve')
        <form method="POST" action="{{ route('dashboard.payments.approve', ['id' => $paymentNumericId]) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#10B981] hover:bg-[#0EA371] shadow-[0_4px_14px_rgba(16,185,129,0.3)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('payments.approve') }}
            </button>
        </form>
        @endcan
        @can('payment.process')
        <form method="POST" action="{{ route('dashboard.payments.process', ['id' => $paymentNumericId]) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                {{ __('payments.process') }}
            </button>
        </form>
        @endcan
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Amount breakdown --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('payments.amount_breakdown') }}</h3>
            <div class="space-y-3">
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[13px] text-muted">{{ __('payments.subtotal') }}</span>
                    <span class="text-[16px] font-bold text-primary">{{ $payment['amount'] }}</span>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[13px] text-muted">{{ __('payments.vat') }} (5%)</span>
                    <span class="text-[16px] font-bold text-primary">{{ $payment['vat'] }}</span>
                </div>
                <div class="bg-[#10B981]/5 border border-[#10B981]/20 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[14px] font-bold text-primary">{{ __('payments.total') }}</span>
                    <span class="text-[22px] font-bold text-[#10B981]">{{ $payment['total'] }}</span>
                </div>
            </div>
        </div>

        {{-- Milestone progress --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('payments.milestone_progress') }}</h3>
            <div class="flex items-center gap-3 mb-2">
                <p class="text-[14px] font-bold text-primary">{{ $payment['milestone'] }}</p>
                <span class="text-[11px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5 font-semibold">{{ $payment['pct'] }}%</span>
            </div>
            <div class="w-full h-2 bg-elevated rounded-full overflow-hidden">
                <div class="h-full bg-accent rounded-full" style="width: {{ $payment['pct'] }}%"></div>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('payments.parties') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('payments.from_buyer') }}</dt>
                    <dd class="font-semibold text-primary">{{ $payment['buyer'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('payments.to_supplier') }}</dt>
                    <dd class="font-semibold text-primary">{{ $payment['supplier'] }}</dd>
                </div>
                <div class="pt-3 border-t border-th-border">
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('payments.contract') }}</dt>
                    <dd class="font-mono font-semibold text-accent">{{ $payment['contract'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('common.due_date') }}</dt>
                    <dd class="font-semibold text-primary">{{ $payment['due'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('payments.gateway_ref') }}</dt>
                    <dd class="font-mono text-[11px] text-muted break-all">{{ $payment['gateway_ref'] }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

@endsection
