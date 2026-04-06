@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('bids.details'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <a href="{{ route('dashboard.bids') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <p class="text-[12px] font-mono text-muted mb-2">{{ $bid['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary leading-tight">{{ $bid['rfq_title'] }}</h1>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <x-dashboard.status-badge :status="$bid['status']" />
            <span class="text-[13px] text-muted">{{ __('bids.submitted_on', ['date' => $bid['submitted']]) }}</span>
            <span class="text-faint">·</span>
            <span class="text-[13px] text-muted">{{ __('bids.expires_on', ['date' => $bid['expires']]) }}</span>
        </div>
    </div>
    <div class="flex items-center gap-3">
        @if($bid['status'] === 'submitted' || $bid['status'] === 'under_review')
            @can('bid.accept')
            <form method="POST" action="{{ route('dashboard.bids.accept', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#10B981] hover:bg-[#0EA371] shadow-[0_4px_14px_rgba(16,185,129,0.3)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                    {{ __('bids.accept') }}
                </button>
            </form>
            @endcan
            @can('bid.withdraw')
            <form method="POST" action="{{ route('dashboard.bids.withdraw', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
                    {{ __('bids.withdraw') }}
                </button>
            </form>
            @endcan
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Pricing summary --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('bids.pricing_summary') }}</h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('bids.bid_amount') }}</p>
                    <p class="text-[20px] font-bold text-[#10B981]">{{ $bid['amount'] }}</p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('bids.rfq_budget') }}</p>
                    <p class="text-[20px] font-bold text-primary">{{ $bid['old_amount'] }}</p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('bids.diff') }}</p>
                    <p class="text-[20px] font-bold {{ $bid['price_up'] ? 'text-[#EF4444]' : 'text-[#10B981]' }}">
                        {{ $bid['price_up'] ? '+' : '−' }}{{ $bid['diff'] }}%
                    </p>
                </div>
            </div>
        </div>

        {{-- Line items --}}
        @if(!empty($bid['items']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('bids.line_items') }}</h3>
            <div class="space-y-3">
                @foreach($bid['items'] as $item)
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] text-muted">Item #{{ $item['n'] }}</p>
                        <p class="text-[14px] font-bold text-primary">{{ $item['name'] }}</p>
                        <p class="text-[11px] text-muted">{{ __('pr.quantity') }}: {{ $item['qty'] }}</p>
                    </div>
                    <p class="text-[16px] font-bold text-accent">{{ $item['unit_price'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Notes --}}
        @if(!empty($bid['notes']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-3">{{ __('bids.notes') }}</h3>
            <p class="text-[13px] text-body leading-relaxed">{{ $bid['notes'] }}</p>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Supplier --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('bids.supplier') }}</h3>
            <p class="text-[14px] font-bold text-primary mb-1">{{ $bid['supplier'] }}</p>
            <p class="text-[12px] text-muted">{{ __('bids.rfq_ref') }}:
                @if($bid['rfq_numeric_id'])
                    <a href="{{ route('dashboard.rfqs.show', ['id' => $bid['rfq_numeric_id']]) }}" class="text-accent">{{ $bid['rfq'] }}</a>
                @else
                    <span class="text-body">{{ $bid['rfq'] }}</span>
                @endif
            </p>
        </div>

        {{-- Terms --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('bids.terms') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('bids.delivery_days') }}:</dt>
                    <dd class="font-semibold text-primary">{{ $bid['days'] }} days</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('bids.payment_terms') }}:</dt>
                    <dd class="font-semibold text-primary">{{ $bid['terms'] }}</dd>
                </div>
                @if($bid['ai_score'] !== null)
                <div class="flex items-center justify-between pt-3 border-t border-th-border">
                    <dt class="text-muted">{{ __('bids.ai_score') }}:</dt>
                    <dd class="font-bold text-accent text-[16px]">{{ $bid['ai_score'] }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>

@endsection
