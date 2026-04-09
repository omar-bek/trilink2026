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
        {{-- Tax invoice card (Phase 1 — UAE Compliance Roadmap) --}}
        @if($taxInvoiceView)
            <div class="bg-surface border border-th-border rounded-2xl p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-[15px] font-bold text-primary">{{ __('tax_invoices.card_title') }}</h3>
                        <p class="text-[11px] text-muted mt-0.5">{{ __('tax_invoices.card_subtitle') }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 h-[22px] px-2 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 text-[#00d9b5] text-[10px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                        {{ __('tax_invoices.status_issued') }}
                    </span>
                </div>
                <dl class="space-y-3 text-[13px]">
                    <div>
                        <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('tax_invoices.col_number') }}</dt>
                        <dd class="font-mono font-semibold text-accent break-all">{{ $taxInvoiceView['invoice_number'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('tax_invoices.col_issue_date') }}</dt>
                        <dd class="font-semibold text-primary">{{ $taxInvoiceView['issue_date'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('tax_invoices.vat_short') }}</dt>
                        <dd class="font-semibold text-primary">{{ $taxInvoiceView['vat'] }}</dd>
                    </div>
                    <div class="pt-3 border-t border-th-border">
                        <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('common.total') }}</dt>
                        <dd class="text-[18px] font-bold text-[#00d9b5]">{{ $taxInvoiceView['total'] }}</dd>
                    </div>
                </dl>
                @if($taxInvoiceView['has_pdf'])
                    <a href="{{ $taxInvoiceView['download_url'] }}"
                       class="mt-4 w-full inline-flex items-center justify-center gap-2 h-11 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        {{ __('tax_invoices.download_pdf') }}
                    </a>
                @else
                    <p class="mt-4 text-[12px] text-muted text-center">{{ __('tax_invoices.pdf_rendering') }}</p>
                @endif
            </div>
        @elseif($payment['paid'])
            {{-- Payment is completed but invoice hasn't been issued yet —
                 either the job is still pending or it failed. Only the
                 buyer (or an admin) can force-retry. --}}
            <div class="bg-surface border border-[#ffb020]/30 rounded-2xl p-6">
                <div class="flex items-start gap-3 mb-3">
                    <svg class="w-5 h-5 text-[#ffb020] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <div>
                        <h3 class="text-[14px] font-bold text-primary">{{ __('tax_invoices.not_yet_issued') }}</h3>
                        <p class="text-[12px] text-muted mt-1">{{ __('tax_invoices.not_yet_issued_hint') }}</p>
                    </div>
                </div>
                @can('payment.process')
                    <form method="POST" action="{{ route('dashboard.payments.invoice.issue', ['id' => $payment['db_id']]) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-xl text-[12px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/30 hover:bg-[#ffb020]/15 transition-colors">
                            {{ __('tax_invoices.retry_issue') }}
                        </button>
                    </form>
                @endcan
            </div>
        @endif

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
