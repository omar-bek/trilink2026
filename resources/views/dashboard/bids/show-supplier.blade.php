@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', $bid['id'])

@php
$statusPills = [
    'submitted'    => ['text' => 'text-accent', 'bg' => 'bg-accent/10', 'border' => 'border-accent/20', 'dot' => 'bg-accent', 'label' => __('status.under_review')],
    'under_review' => ['text' => 'text-accent', 'bg' => 'bg-accent/10', 'border' => 'border-accent/20', 'dot' => 'bg-accent', 'label' => __('status.under_review')],
    'accepted'     => ['text' => 'text-[#00d9b5]', 'bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/20', 'dot' => 'bg-[#00d9b5]', 'label' => __('status.accepted')],
    'rejected'     => ['text' => 'text-[#ff4d7f]', 'bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/20', 'dot' => 'bg-[#ff4d7f]', 'label' => __('status.rejected')],
    'draft'        => ['text' => 'text-muted', 'bg' => 'bg-muted/10', 'border' => 'border-muted/20', 'dot' => 'bg-muted', 'label' => __('status.draft')],
];
$pill = $statusPills[$bid['status']] ?? $statusPills['draft'];
@endphp

@section('content')

{{-- Header: back + BID id + status + action buttons --}}
<div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <a href="{{ route('dashboard.bids') }}"
           class="w-10 h-10 rounded-[12px] bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary hover:border-accent/40 flex-shrink-0 transition-colors">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ $bid['id'] }}</h1>
                <span class="inline-flex items-center gap-2 h-[26px] px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                    <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>
                    {{ $pill['label'] }}
                </span>
            </div>
            <p class="text-[14px] text-muted mt-1">{{ __('bids.bid_for_rfq', ['rfq' => $bid['rfq']]) }} · {{ $bid['rfq_title'] }}</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('dashboard.bids.pdf', ['id' => $bid['numeric_id']]) }}"
           class="inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            {{ __('common.download') }}
        </a>
        @if($bid['can_withdraw'])
        <a href="{{ route('dashboard.negotiations.show', ['id' => $bid['numeric_id']]) }}"
           class="inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
            {{ __('bids.edit_bid') }}
        </a>
        <form method="POST" action="{{ route('dashboard.bids.withdraw', ['id' => $bid['numeric_id']]) }}"
              onsubmit="return confirm('{{ __('bids.withdraw_confirm') }}')">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/15 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                {{ __('bids.withdraw') }}
            </button>
        </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- LEFT: Main content --}}
    <div class="lg:col-span-2 space-y-4">
        {{-- ===== Tax Invoice card (Phase 2 — replaces the old "Total amount" card) ===== --}}
        <div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-[25px] py-4 border-b border-th-border bg-surface-2/30 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.5-4-3.5S9.79 5 12 5c1.128 0 2.147.373 2.854.968l.875.675"/></svg>
                    </div>
                    <div>
                        <p class="text-[15px] font-semibold text-primary">{{ __('bids.tax_invoice_breakdown') }}</p>
                        <p class="text-[12px] text-muted">{{ __('bids.tax_treatment_' . ($bid['vat']['treatment'] ?? 'exclusive')) }}</p>
                    </div>
                </div>
                <p class="text-[12px] text-muted">{{ __('bids.submitted_date') }}: <span class="text-primary font-medium">{{ $bid['submitted'] }}</span></p>
            </div>
            <div class="px-[25px] py-5 space-y-2.5">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-muted">{{ __('bids.subtotal') }}</span>
                    <span class="text-primary font-mono">{{ $bid['vat']['subtotal_fmt'] }}</span>
                </div>
                <div class="flex items-center justify-between text-[13px]">
                    @if(($bid['vat']['treatment'] ?? '') === 'not_applicable')
                    <span class="text-muted">{{ __('bids.vat') }} (0%)</span>
                    @else
                    <span class="text-muted">{{ __('bids.vat') }} ({{ rtrim(rtrim(number_format($bid['vat']['rate'], 2), '0'), '.') }}%)</span>
                    @endif
                    <span class="text-primary font-mono">{{ $bid['vat']['tax_amount_fmt'] }}</span>
                </div>
                @if(!empty($bid['vat']['exemption_reason']))
                <p class="text-[11px] text-muted">{{ __('bids.exemption_reason_label') }}: <span class="text-primary">{{ __('bids.tax_exempt_' . $bid['vat']['exemption_reason']) }}</span></p>
                @endif
                <div class="flex items-center justify-between pt-3 border-t border-th-border">
                    <span class="text-[14px] font-bold text-primary">{{ __('common.total') }}</span>
                    <span class="text-[20px] font-bold text-[#00d9b5] font-mono">{{ $bid['vat']['total_fmt'] }}</span>
                </div>
                <p class="text-[12px] text-muted pt-1">{{ __('bids.valid_until_short', ['date' => $bid['valid_until']]) }}</p>
            </div>
        </div>

        {{-- ===== Trade Terms card (Incoterm + Country of origin + HS code) ===== --}}
        @if(!empty($bid['incoterm']) || !empty($bid['country_of_origin']) || !empty($bid['hs_code']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.trade_terms') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @if(!empty($bid['incoterm']))
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <p class="text-[11px] text-muted uppercase tracking-wider font-semibold mb-2">{{ __('bids.incoterm') }}</p>
                    <p class="text-[16px] font-bold text-primary font-mono">{{ $bid['incoterm'] }}</p>
                    <p class="text-[11px] text-muted mt-1">{{ __('bids.incoterm_' . strtolower($bid['incoterm']) . '_label') }}</p>
                </div>
                @endif
                @if(!empty($bid['country_of_origin']))
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <p class="text-[11px] text-muted uppercase tracking-wider font-semibold mb-2">{{ __('bids.country_of_origin') }}</p>
                    <p class="text-[14px] font-semibold text-primary">{{ $bid['country_of_origin_name'] }}</p>
                    <p class="text-[11px] text-muted mt-1 font-mono">{{ $bid['country_of_origin'] }}</p>
                </div>
                @endif
                @if(!empty($bid['hs_code']))
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <p class="text-[11px] text-muted uppercase tracking-wider font-semibold mb-2">{{ __('bids.hs_code') }}</p>
                    <p class="text-[14px] font-semibold text-primary font-mono">{{ $bid['hs_code'] }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Bid Items --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.bid_items') }}</h3>
            <div class="space-y-3">
                @forelse($bid['items'] as $item)
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <p class="text-[14px] font-medium text-primary">{{ $item['name'] }}</p>
                        <p class="text-[14px] font-semibold text-[#00d9b5]">{{ $item['total'] }}</p>
                    </div>
                    <div class="flex items-center gap-4 text-[12px] text-muted flex-wrap">
                        <span>{{ __('rfq.quantity') }}: <span class="text-primary">{{ number_format($item['qty']) }} {{ $item['unit'] }}</span></span>
                        <span>{{ __('bids.unit_price') }}: <span class="text-primary">{{ $item['unit_price'] }}</span></span>
                        <span>{{ __('common.total') }}: <span class="text-primary">{{ $item['total'] }}</span></span>
                    </div>
                </div>
                @empty
                <p class="text-[13px] text-muted text-center py-4">{{ __('bids.no_itemized_breakdown') }}</p>
                @endforelse
            </div>
            <div class="flex items-center justify-between pt-4 mt-4 border-t border-th-border">
                <p class="text-[14px] font-medium text-primary">{{ __('bids.total_bid_amount') }}</p>
                <p class="text-[20px] font-semibold text-[#00d9b5]">{{ $bid['amount'] }}</p>
            </div>
        </div>

        {{-- Payment Schedule table --}}
        <x-payment-schedule
            :rows="$bid['payment_schedule']"
            :total="$bid['amount']"
            :title="__('bids.payment_schedule')"
            :subtitle="__('bids.payment_schedule_view_hint')" />

        {{-- Terms & Conditions (delivery + warranty) --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.tab_terms') }}</h3>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-accent flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    <div>
                        <p class="text-[14px] font-medium text-primary">{{ __('bids.delivery_timeline') }}</p>
                        <p class="text-[13px] text-muted mt-0.5">{{ __('bids.days_from_signing', ['days' => $bid['delivery_days']]) }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#ffb020] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <div>
                        <p class="text-[14px] font-medium text-primary">{{ __('bids.warranty') }}</p>
                        <p class="text-[13px] text-muted mt-0.5">{{ $bid['warranty'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Additional Notes --}}
        @if($bid['notes'])
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-3">{{ __('bids.additional_notes') }}</h3>
            <p class="text-[14px] text-muted leading-[22px]">{{ $bid['notes'] }}</p>
        </div>
        @endif

        {{-- Negotiation Rounds --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <x-negotiation-rounds :bid="$bid" :data="$bid['negotiation']" />
        </div>

        {{-- Attached Documents --}}
        @if(!empty($bid['documents']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.attached_documents') }}</h3>
            <div class="space-y-3">
                @foreach($bid['documents'] as $doc)
                <a href="{{ $doc['url'] }}" class="flex items-center justify-between gap-3 bg-page border border-th-border rounded-[12px] p-4 hover:border-accent/40 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-[10px] bg-accent/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[14px] font-medium text-primary truncate">{{ $doc['name'] }}</p>
                            <p class="text-[12px] text-muted">{{ $doc['type'] }} · {{ $doc['size'] }}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-muted flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- RIGHT sidebar: status timeline + buyer info + competition --}}
    <div class="space-y-4">
        {{-- Bid Status timeline --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.bid_status') }}</h3>
            <div class="space-y-4">
                @foreach($bid['timeline'] as $step)
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 {{ $step['done'] ? 'bg-[#00d9b5]/20' : 'bg-page border border-th-border' }}">
                        @if($step['done'])
                        <svg class="w-3.5 h-3.5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/></svg>
                        @else
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[14px] font-medium {{ $step['done'] ? 'text-primary' : 'text-muted' }}">{{ $step['title'] }}</p>
                        <p class="text-[12px] text-muted mt-0.5">{{ $step['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Insurance — buyer-visible trust signal pulled from your active,
             verified company_insurances. Empty array = no policies on file
             (or all expired); we hide the card entirely instead of showing
             "—" so the sidebar stays compact. --}}
        @if(!empty($bid['insurance_policies']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                {{ __('bids.insurance_coverage') }}
            </h3>
            <div class="space-y-2">
                @foreach($bid['insurance_policies'] as $policy)
                <div class="bg-page border border-th-border rounded-[10px] p-3">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="text-[12px] font-semibold text-primary">{{ __('bids.insurance_type_' . str_replace(' ', '_', $policy['type'])) }}</p>
                        <span class="inline-flex items-center gap-1 text-[10px] text-[#00d9b5] font-semibold">
                            <span class="w-1 h-1 rounded-full bg-[#00d9b5]"></span>
                            {{ __('bids.verified') }}
                        </span>
                    </div>
                    <p class="text-[11px] text-muted">{{ $policy['insurer'] }}</p>
                    <p class="text-[11px] text-muted mt-1">{{ __('bids.coverage') }}: <span class="text-primary font-medium">{{ $policy['coverage'] }}</span></p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Buyer Information --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.buyer_information') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-muted mb-1">{{ __('bids.company_name') }}</dt>
                    <dd class="text-primary font-medium">{{ $bid['buyer']['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-muted mb-1">{{ __('bids.rfq_ref') }}</dt>
                    <dd class="text-primary font-medium">{{ $bid['buyer']['rfq_ref'] }}</dd>
                </div>
                <div>
                    <dt class="text-muted mb-1">{{ __('rfq.category') }}</dt>
                    <dd class="text-primary font-medium">{{ $bid['buyer']['category'] }}</dd>
                </div>
            </dl>
        </div>

        {{-- Competition --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('bids.competition') }}</h3>
            <div class="text-center mb-4">
                <p class="text-[36px] font-bold text-accent leading-none">{{ $bid['competition']['count'] }}</p>
                <p class="text-[13px] text-muted mt-1">{{ __('bids.total_bids') }}</p>
            </div>
            <dl class="space-y-3 text-[13px] pt-4 border-t border-th-border">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('bids.your_position') }}</dt>
                    <dd class="text-[#00d9b5] font-semibold">{{ $bid['competition']['my_position'] ? '#' . $bid['competition']['my_position'] : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('bids.lowest_bid') }}</dt>
                    <dd class="text-[#00d9b5] font-medium">{{ $bid['competition']['lowest'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('bids.your_bid') }}</dt>
                    <dd class="text-[#00d9b5] font-medium">{{ $bid['competition']['my_bid'] }}</dd>
                </div>
            </dl>
        </div>

        {{-- Actions: View Original RFQ + Contact Buyer --}}
        @if($bid['rfq_numeric_id'])
        <a href="{{ route('dashboard.rfqs.show', ['id' => $bid['rfq_numeric_id']]) }}"
           class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            {{ __('bids.view_original_rfq') }}
        </a>
        @endif
        <a href="{{ route('dashboard.negotiations.show', ['id' => $bid['numeric_id']]) }}"
           class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] text-[14px] font-medium text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            {{ __('bids.contact_buyer') }}
        </a>
    </div>
</div>

@endsection
