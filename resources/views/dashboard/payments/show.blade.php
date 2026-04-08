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
        @can('payment.approve')
        <form method="POST" action="{{ route('dashboard.payments.approve', ['id' => $payment['db_id']]) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] shadow-[0_4px_14px_rgba(0,217,181,0.3)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('payments.approve') }}
            </button>
        </form>
        @endcan
        @can('payment.process')
        <form method="POST" action="{{ route('dashboard.payments.process', ['id' => $payment['db_id']]) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                {{ __('payments.process') }}
            </button>
        </form>
        @endcan
    </div>
    @endif
</div>

@if(session('status'))
<div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">
    {{ session('status') }}
</div>
@endif
@if($errors->any())
<div class="mb-6 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[13px] text-[#ff4d7f] font-semibold">
    {{ $errors->first() }}
</div>
@endif

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
                <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/20 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[14px] font-bold text-primary">{{ __('payments.total') }}</span>
                    <span class="text-[22px] font-bold text-[#00d9b5]">{{ $payment['total'] }}</span>
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

        {{-- Activity timeline --}}
        @if(!empty($timeline))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('payments.activity') }}</h3>
            <ol class="space-y-4">
                @foreach($timeline as $event)
                <li class="flex items-start gap-3">
                    <span class="mt-1.5 w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $event['color'] }};"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-primary">{{ $event['label'] }}</p>
                        <p class="text-[11px] text-muted">{{ $event['time'] }}</p>
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
        @endif
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
                    <dd class="font-mono font-semibold">
                        @if($payment['contract_url'])
                            <a href="{{ $payment['contract_url'] }}" class="text-accent hover:underline">{{ $payment['contract'] }}</a>
                        @else
                            <span class="text-accent">{{ $payment['contract'] }}</span>
                        @endif
                    </dd>
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
