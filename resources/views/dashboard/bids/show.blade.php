@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('bids.details'))

@section('content')

<div x-data="{ tab: 'details' }">

    {{-- ===== Header: back link + title + amount + actions ===== --}}
    <div class="flex items-start justify-between gap-6 mb-8 flex-wrap">
        <div class="flex items-start gap-4 flex-1 min-w-0">
            <a href="{{ route('dashboard.bids') }}" class="w-10 h-10 rounded-xl bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary transition-colors flex-shrink-0">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-3 mb-2 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">{{ $bid['id'] }}</span>
                    <x-dashboard.status-badge :status="$bid['status']" />
                    {{-- Phase 2 / Sprint 8 / task 2.8 — supplier verification
                         tier badge alongside the bid status. Hidden when the
                         supplier is unverified to keep the header clean. --}}
                    @if(!empty($bid['supplier_info']['verification_level']))
                        <x-dashboard.verification-badge :level="$bid['supplier_info']['verification_level']" />
                    @endif
                    @if(!empty($bid['is_registered_supplier']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-[#3B82F6] bg-[#3B82F6]/10 border border-[#3B82F6]/20"
                          title="{{ __('bids.registered_supplier_hint') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('bids.registered_supplier_badge') }}
                    </span>
                    @endif
                    @if(!empty($bid['shortlisted']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                        {{ __('bids.shortlisted') }}
                    </span>
                    @endif
                </div>
                @if(!empty($bid['supplier_info']['id']))
                    <a href="{{ route('dashboard.suppliers.profile', ['id' => $bid['supplier_info']['id']]) }}"
                       class="block text-[28px] sm:text-[34px] font-bold text-primary hover:text-accent leading-tight truncate transition-colors">{{ $bid['supplier'] }}</a>
                @else
                    <h1 class="text-[28px] sm:text-[34px] font-bold text-primary leading-tight truncate">{{ $bid['supplier'] }}</h1>
                @endif
                <div class="flex items-center gap-3 mt-2 text-[13px] text-muted flex-wrap">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        @if($bid['rfq_numeric_id'])
                            <a href="{{ route('dashboard.rfqs.show', ['id' => $bid['rfq_numeric_id']]) }}" class="hover:text-accent">{{ $bid['rfq'] }} · {{ $bid['rfq_title'] }}</a>
                        @else
                            <span>{{ $bid['rfq'] }} · {{ $bid['rfq_title'] }}</span>
                        @endif
                    </span>
                    @if(!empty($bid['supplier_info']['rating']))
                    <span class="text-faint">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                        {{ $bid['supplier_info']['rating'] }} ({{ $bid['supplier_info']['reviews'] }} {{ __('bids.reviews') }})
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: amount --}}
        <div class="text-end flex-shrink-0">
            <p class="text-[34px] sm:text-[40px] font-bold text-accent leading-none">{{ $bid['amount'] }}</p>
            <div class="flex items-center justify-end gap-2 mt-2">
                <span class="text-[12px] text-faint line-through">{{ $bid['old_amount'] }}</span>
                <span class="text-[12px] font-bold {{ $bid['price_up'] ? 'text-[#ff4d7f]' : 'text-[#00d9b5]' }} inline-flex items-center gap-0.5">
                    @if($bid['price_up'])<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 5l8 8H4z"/></svg>@else<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 19l-8-8h16z"/></svg>@endif
                    {{ __('bids.save') }} {{ $bid['savings'] }} ({{ $bid['diff'] }}%)
                </span>
            </div>
        </div>
    </div>

    {{-- ===== Action buttons row ===== --}}
    <div class="flex items-center gap-3 mb-8 flex-wrap">
        @if($bid['status'] === 'submitted' || $bid['status'] === 'under_review')
            @can('bid.accept')
            <form method="POST" action="{{ route('dashboard.bids.accept', ['id' => $bid['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] shadow-[0_4px_14px_rgba(0,217,181,0.3)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('bids.accept_bid') }}
                </button>
            </form>
            @endcan

            <a href="{{ route('dashboard.negotiations.show', ['id' => $bid['numeric_id']]) }}"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                {{ __('bids.start_negotiation') }}
            </a>

            @can('bid.accept')
            {{-- Single-bid reject goes through the bulk-reject endpoint with
                 a single id; the controller already validates state and
                 ownership the same way bulk does. --}}
            <form method="POST" action="{{ route('dashboard.bids.bulk-reject') }}" class="inline"
                  onsubmit="return confirm('{{ __('bids.reject_confirm') }}')">
                @csrf
                <input type="hidden" name="ids[]" value="{{ $bid['numeric_id'] }}">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6m-6 0l6-6"/></svg>
                    {{ __('bids.reject_bid') }}
                </button>
            </form>
            @endcan
        @endif
    </div>

    {{-- ===== Tabs container ===== --}}
    <div class="bg-surface border border-th-border rounded-2xl overflow-hidden">

        {{-- Tab nav --}}
        <div class="flex items-center gap-1 border-b border-th-border px-4 pt-4">
            @php
            $tabs = [
                ['key' => 'details',     'label' => __('bids.tab_details'),     'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625'],
                ['key' => 'terms',       'label' => __('bids.tab_terms'),       'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['key' => 'negotiation', 'label' => __('bids.tab_negotiation'), 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                ['key' => 'documents',   'label' => __('bids.tab_documents'),   'icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3'],
                ['key' => 'history',     'label' => __('bids.tab_history'),     'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
            ];
            @endphp
            @foreach($tabs as $t)
            <button type="button"
                    @click="tab = '{{ $t['key'] }}'"
                    :class="tab === '{{ $t['key'] }}' ? 'bg-accent text-white' : 'text-muted hover:text-primary hover:bg-surface-2'"
                    class="inline-flex items-center gap-2 px-5 py-3 rounded-t-xl text-[13px] font-semibold transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                {{ $t['label'] }}
            </button>
            @endforeach
        </div>

        {{-- ===== Tab 1: Bid Details ===== --}}
        <div x-show="tab === 'details'" class="p-6 sm:p-8 space-y-8">

            {{-- Supplier Information --}}
            <section>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-[18px] font-bold text-primary">{{ __('bids.supplier_info') }}</h3>
                    @if(!empty($bid['supplier_info']['id']))
                    <a href="{{ route('dashboard.suppliers.profile', ['id' => $bid['supplier_info']['id']]) }}"
                       class="text-[13px] font-medium text-accent hover:underline">{{ __('bids.view_profile') }} →</a>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php
                    $infoCards = [
                        ['label' => __('bids.company_name'), 'value' => $bid['supplier_info']['name'],         'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25'],
                        ['label' => __('bids.location'),     'value' => $bid['supplier_info']['location'],     'icon' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
                        ['label' => __('bids.rating'),       'value' => $bid['supplier_info']['rating']
                            ? $bid['supplier_info']['rating'] . ' / 5.0 (' . $bid['supplier_info']['reviews'] . ' ' . __('bids.reviews') . ')'
                            : __('bids.no_reviews_yet'), 'icon' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442'],
                        ['label' => __('bids.completed_orders'), 'value' => $bid['supplier_info']['completed'] . ' ' . __('bids.orders'), 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => __('bids.years_in_business'), 'value' => $bid['supplier_info']['years'] . ' ' . __('common.years'),   'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                        ['label' => __('bids.registration'),  'value' => $bid['supplier_info']['registration'], 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192'],
                    ];
                    @endphp
                    @foreach($infoCards as $card)
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
                            {{ $card['label'] }}
                        </div>
                        <p class="text-[14px] font-semibold text-primary">{{ $card['value'] }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Certifications --}}
                <div class="mt-5 bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-3">{{ __('bids.certifications') }}</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        @forelse($bid['supplier_info']['certifications'] as $cert)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-semibold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20">{{ $cert }}</span>
                        @empty
                        <span class="text-[12px] text-faint">—</span>
                        @endforelse
                    </div>
                </div>

                {{-- Phase 2 — Insurance coverage badges, pulled from
                     company_insurances. Hidden when the supplier has no
                     active verified policies on file. --}}
                @if(!empty($bid['insurance_policies']))
                <div class="mt-3 bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-3 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                        {{ __('bids.insurance_coverage') }}
                    </p>
                    <div class="flex items-center gap-2 flex-wrap">
                        @foreach($bid['insurance_policies'] as $policy)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20"
                              title="{{ $policy['insurer'] }} · {{ __('bids.coverage') }} {{ $policy['coverage'] }} · {{ $policy['expires'] }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/></svg>
                            {{ __('bids.insurance_type_' . str_replace(' ', '_', $policy['type'])) }}
                            <span class="text-[10px] text-muted font-normal">· {{ $policy['coverage'] }}</span>
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Phase 2 — Trade terms: Incoterm + Country of Origin + HS code --}}
                @if(!empty($bid['incoterm']) || !empty($bid['country_of_origin']) || !empty($bid['hs_code']))
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @if(!empty($bid['incoterm']))
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('bids.incoterm') }}</p>
                        <p class="text-[16px] font-bold text-primary font-mono">{{ $bid['incoterm'] }}</p>
                        <p class="text-[11px] text-muted mt-1">{{ __('bids.incoterm_' . strtolower($bid['incoterm']) . '_label') }}</p>
                    </div>
                    @endif
                    @if(!empty($bid['country_of_origin']))
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('bids.country_of_origin') }}</p>
                        <p class="text-[14px] font-semibold text-primary">{{ $bid['country_of_origin_name'] }}</p>
                        <p class="text-[11px] text-muted mt-1 font-mono">{{ $bid['country_of_origin'] }}</p>
                    </div>
                    @endif
                    @if(!empty($bid['hs_code']))
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('bids.hs_code') }}</p>
                        <p class="text-[14px] font-semibold text-primary font-mono">{{ $bid['hs_code'] }}</p>
                    </div>
                    @endif
                </div>
                @endif
            </section>

            {{-- ===== Tax Invoice breakdown (Phase 2) — replaces the old single-line "Pricing Breakdown" header ===== --}}
            <section>
                <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                    <h3 class="text-[18px] font-bold text-primary">{{ __('bids.tax_invoice_breakdown') }}</h3>
                    @if(!empty($bid['supplier_trn']))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-accent/10 border border-accent/20 text-accent text-[11px] font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        TRN: {{ $bid['supplier_trn'] }}
                    </span>
                    @endif
                </div>
                <div class="bg-page border border-th-border rounded-xl p-5">
                    @if(!empty($bid['items']))
                        <div class="space-y-3">
                            @foreach($bid['items'] as $item)
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[14px] font-bold text-primary">{{ $item['name'] }}</p>
                                    <p class="text-[12px] text-muted mt-0.5">{{ __('pr.quantity') }}: {{ $item['qty'] }} {{ $item['unit'] }} · {{ __('bids.unit_price') }}: {{ $item['unit_price'] }}</p>
                                </div>
                                <p class="text-[18px] font-bold text-accent whitespace-nowrap">{{ $item['unit_price'] }}</p>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[14px] font-bold text-primary">{{ $bid['rfq_title'] }}</p>
                                <p class="text-[12px] text-muted mt-0.5">{{ __('bids.total_bid_value') }}</p>
                            </div>
                            <p class="text-[20px] font-bold text-accent whitespace-nowrap">{{ $bid['amount'] }}</p>
                        </div>
                    @endif

                    {{-- Subtotal / VAT / Total breakdown — driven by the
                         supplier's declared treatment, snapshotted at submit
                         time. --}}
                    <div class="mt-5 pt-4 border-t border-th-border space-y-2">
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
                        <div class="flex items-center justify-between pt-2 border-t border-th-border">
                            <span class="text-[14px] font-bold text-primary">{{ __('common.total') }}</span>
                            <span class="text-[20px] font-bold text-[#00d9b5] font-mono">{{ $bid['vat']['total_fmt'] }}</span>
                        </div>
                        <p class="text-[11px] text-muted pt-1">{{ __('bids.tax_treatment_' . ($bid['vat']['treatment'] ?? 'exclusive')) }}</p>
                    </div>
                </div>
            </section>

            {{-- Delivery Information --}}
            <section>
                <h3 class="text-[18px] font-bold text-primary mb-5">{{ __('bids.delivery_info') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @php
                    $deliveryCards = [
                        ['label' => __('bids.lead_time'),          'value' => $bid['days'] . ' ' . __('common.days'),     'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => __('bids.estimated_delivery'), 'value' => $bid['estimated_delivery'],                 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v11.25'],
                        ['label' => __('bids.shipping_method'),    'value' => $bid['shipping_method'],                    'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0'],
                        ['label' => __('bids.incoterms'),          'value' => $bid['incoterms'],                          'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733'],
                    ];
                    @endphp
                    @foreach($deliveryCards as $card)
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
                            {{ $card['label'] }}
                        </div>
                        <p class="text-[14px] font-semibold text-primary">{{ $card['value'] }}</p>
                    </div>
                    @endforeach
                </div>
            </section>

            {{-- Technical Specifications --}}
            <section>
                <h3 class="text-[18px] font-bold text-primary mb-5">{{ __('bids.tech_specs') }}</h3>
                <div class="bg-page border border-th-border rounded-xl p-5 space-y-4">
                    <div>
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('pr.tech_specs') }}</p>
                        <p class="text-[14px] text-body leading-relaxed">{{ $bid['tech_spec'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('bids.warranty') }}</p>
                        <p class="text-[14px] text-body">{{ $bid['warranty'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('bids.packaging') }}</p>
                        <p class="text-[14px] text-body">{{ $bid['packaging'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('bids.quality_certificates') }}</p>
                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach($bid['quality_certs'] as $c)
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-semibold text-accent bg-accent/10 border border-accent/20">{{ $c }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ===== Tab 2: Terms & Conditions ===== --}}
        <div x-show="tab === 'terms'" x-cloak class="p-6 sm:p-8 space-y-6">
            <section>
                <h3 class="text-[18px] font-bold text-primary mb-5">{{ __('bids.payment_terms') }}</h3>
                <p class="text-[13px] text-muted mb-4">{{ $bid['terms'] }}</p>

                {{-- Full milestone-based payment schedule (replaces the old 2-card advance/final split) --}}
                <x-payment-schedule
                    :rows="$bid['payment_schedule']"
                    :total="$bid['amount']"
                    title="{{ __('bids.payment_schedule') ?? 'Payment Schedule' }}"
                    subtitle="{{ __('bids.payment_schedule_hint') ?? 'Milestone breakdown of the bid amount.' }}" />

                <div class="bg-page border border-th-border rounded-xl p-5 mt-5">
                    <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('bids.accepted_methods') }}</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        @foreach($bid['payment_methods'] as $m)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-semibold text-primary bg-surface border border-th-border">{{ $m }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-[18px] font-bold text-primary mb-5">{{ __('bids.validity_period') }}</h3>
                <div class="bg-[#ffb020]/5 border border-[#ffb020]/30 rounded-xl p-4 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-[#ffb020]/15 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>
                    <div>
                        <p class="text-[14px] font-bold text-primary">{{ __('bids.valid_until', ['date' => $bid['validity']]) }}</p>
                        <p class="text-[12px] text-muted mt-0.5">{{ __('bids.validity_hint') }}</p>
                    </div>
                </div>
            </section>
        </div>

        {{-- ===== Tab 3: Negotiation Rounds ===== --}}
        <div x-show="tab === 'negotiation'" x-cloak class="p-6 sm:p-8">
            <x-negotiation-rounds :bid="$bid" :data="$bid['negotiation']" />
        </div>

        {{-- ===== Tab 4: Documents ===== --}}
        <div x-show="tab === 'documents'" x-cloak class="p-6 sm:p-8">
            <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                <h3 class="text-[18px] font-bold text-primary">{{ __('bids.attached_documents') }}</h3>
                @if(count($bid['documents']) > 0)
                <button type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    {{ __('bids.download_all') }}
                </button>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($bid['documents'] as $doc)
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[14px] font-semibold text-primary truncate">{{ $doc['name'] }}</p>
                        <p class="text-[11px] text-muted">{{ $doc['size'] }} · {{ __('bids.uploaded') }} {{ $doc['uploaded'] }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ $doc['url'] }}" target="_blank" class="w-9 h-9 rounded-lg bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary" title="{{ __('common.view') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <a href="{{ $doc['url'] }}" download class="w-9 h-9 rounded-lg bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary" title="{{ __('common.download') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </a>
                    </div>
                </div>
                @empty
                <div class="bg-page border border-th-border rounded-xl p-8 text-center text-[13px] text-muted">
                    {{ __('bids.no_documents') }}
                </div>
                @endforelse
            </div>
        </div>

        {{-- ===== Tab 4: History ===== --}}
        <div x-show="tab === 'history'" x-cloak class="p-6 sm:p-8">
            <div class="space-y-4">
                @foreach($bid['history'] as $event)
                <div class="bg-page border border-th-border rounded-xl p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-accent/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
                            <p class="text-[14px] font-bold text-primary">{{ $event['title'] }}</p>
                            <span class="text-[12px] text-faint">{{ $event['when'] }}</span>
                        </div>
                        <p class="text-[12px] text-muted mb-1">{{ $event['who'] }}</p>
                        <p class="text-[13px] text-body">{{ $event['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

@endsection
