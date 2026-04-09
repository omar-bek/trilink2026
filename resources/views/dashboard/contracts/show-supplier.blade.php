@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', $contract['id'])

@php
$statusPills = [
    'active'    => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'label' => 'Manufacturing'],
    'pending'   => ['bg' => 'bg-[rgba(79,124,255,0.1)]', 'border' => 'border-[rgba(79,124,255,0.2)]', 'text' => 'text-[#4f7cff]', 'label' => 'Pending'],
    'completed' => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]', 'text' => 'text-[#00d9b5]', 'label' => 'Completed'],
    'closed'    => ['bg' => 'bg-[rgba(180,182,192,0.1)]','border' => 'border-[rgba(180,182,192,0.2)]','text' => 'text-[#b4b6c0]', 'label' => 'Closed'],
];
$pill = $statusPills[$contract['status']] ?? $statusPills['active'];

$milestoneColor = [
    'paid'    => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'text' => 'text-[#00d9b5]', 'label_bg' => 'bg-[rgba(0,217,181,0.1)]',  'label_text' => 'text-[#00d9b5]',  'label' => 'Completed'],
    'pending' => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'text' => 'text-[#ffb020]', 'label_bg' => 'bg-[rgba(255,176,32,0.1)]', 'label_text' => 'text-[#ffb020]', 'label' => 'In Progress'],
    'future'  => ['bg' => 'bg-[rgba(180,182,192,0.1)]','text' => 'text-[#b4b6c0]', 'label_bg' => 'bg-[rgba(180,182,192,0.1)]','label_text' => 'text-[#b4b6c0]','label' => 'Pending'],
];
@endphp

@section('content')

{{-- Flash messages --}}
@if(session('status'))
<div class="mb-4 p-4 rounded-[12px] bg-[rgba(0,217,181,0.1)] border border-[rgba(0,217,181,0.3)] text-[13px] text-[#00d9b5] font-medium">
    {{ session('status') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 p-4 rounded-[12px] bg-[rgba(239,68,68,0.1)] border border-[rgba(239,68,68,0.3)] text-[13px] text-[#ef4444] font-medium">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <a href="{{ route('dashboard.contracts') }}"
           class="w-10 h-10 rounded-[12px] bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] flex items-center justify-center text-[#b4b6c0] hover:text-white hover:border-[#4f7cff]/40 flex-shrink-0 transition-colors">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[28px] sm:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ $contract['id'] }}</h1>
                <span class="inline-flex items-center h-[26px] px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                    {{ $contract['progress_label'] ?: $pill['label'] }}
                </span>
            </div>
            <p class="text-[14px] text-[#b4b6c0] mt-1">{{ $contract['title'] }}</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'ar']) }}"
           class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            {{ __('contracts.download_ar') }}
        </a>
        <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'en']) }}"
           class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            {{ __('contracts.download_en') }}
        </a>
    </div>
</div>

{{-- Pre-signature alert: surfaces the sign / upload-signature CTA at
     the top of the supplier view too. Mirrors the buyer-side banner so
     the supplier never has to hunt for the signature button. --}}
@if($contract['status'] === 'pending')
<div class="mb-6 bg-gradient-to-r from-[#4f7cff]/15 to-[#00d9b5]/10 border border-[#4f7cff]/30 rounded-[16px] p-5 flex items-start justify-between gap-4 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-10 h-10 rounded-[12px] bg-[#4f7cff]/20 text-[#4f7cff] flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
        </div>
        <div class="min-w-0">
            <p class="text-[15px] font-bold text-white">{{ __('contracts.created_from_bid') }}</p>
            <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ __('contracts.amendment_window_hint') }}</p>
        </div>
    </div>
    @if($contract['can_sign'])
        @if($contract['needs_signature_assets'])
            <button type="button" @click="$dispatch('open-signature-modal')"
                    class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                {{ __('contracts.upload_signature_cta') }}
            </button>
        @else
            <button type="button" @click="$dispatch('open-sign-modal')"
                    class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
                {{ __('contracts.sign_contract') }}
            </button>
        @endif
    @endif
</div>
@endif

{{-- Progress bar card --}}
<div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px] mb-6">
    <div class="flex items-start justify-between gap-4 mb-3 flex-wrap">
        <div>
            <p class="text-[14px] text-[#b4b6c0] mb-1">Contract Progress</p>
            <p class="text-[32px] font-bold text-white leading-none">{{ $contract['progress'] }}%</p>
        </div>
        @if($contract['days_remaining'] !== null)
        <div class="text-end">
            <p class="text-[14px] text-[#b4b6c0] mb-1">Days Remaining</p>
            <p class="text-[32px] font-bold text-[#ffb020] leading-none">{{ $contract['days_remaining'] }}</p>
        </div>
        @endif
    </div>
    <div class="w-full h-2 bg-[#252932] rounded-full overflow-hidden mt-4">
        <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $contract['progress'] }}%"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- LEFT: Tabs + content --}}
    <div class="lg:col-span-2">
        <div
            x-data="{
                tab: (window.location.hash || '').replace('#', '') || 'overview',
                form: null,
                open(f) { this.form = f; this.tab = 'terms'; window.location.hash = 'terms'; },
                close() { this.form = null; },
                init() {
                    this.$watch('tab', (v) => { if (v) window.location.hash = v; });
                    window.addEventListener('hashchange', () => {
                        this.tab = (window.location.hash || '').replace('#', '') || 'overview';
                    });
                }
            }"
            class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]"
        >
            <div class="flex items-center gap-6 border-b border-[rgba(255,255,255,0.1)] mb-6 -mx-[25px] px-[25px] overflow-x-auto">
                @foreach([
                    'overview'  => 'Overview',
                    'items'     => 'Items',
                    'payments'  => 'Payments',
                    'terms'     => __('contracts.terms_conditions'),
                    'documents' => 'Documents',
                ] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'text-[#4f7cff] border-[#4f7cff]' : 'text-[#b4b6c0] border-transparent hover:text-white'"
                        class="pb-3 text-[14px] font-medium border-b-2 transition-colors whitespace-nowrap">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Overview tab --}}
            <div x-show="tab === 'overview'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Buyer</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['buyer_contact']['name'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Contract Value</p>
                        <p class="text-[14px] font-medium text-[#00d9b5]">{{ $contract['total_amount'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Start Date</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['start_date'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Expected Delivery</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['end_date'] }}</p>
                    </div>
                </div>

                {{-- Parties + legal identity strip + signature audit. The
                     supplier needs to see the same legal context the buyer
                     does (TRN, jurisdiction, registration #) so they can
                     verify the counter-party before signing. --}}
                <h3 class="text-[16px] font-semibold text-white mb-3">{{ __('contracts.parties') }}</h3>
                <div class="space-y-3 mb-6">
                    @foreach($contract['parties'] as $party)
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-[12px] {{ $party['color'] }} text-white font-bold flex items-center justify-center flex-shrink-0" aria-hidden="true">{{ $party['code'] }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <p class="text-[14px] font-bold text-white truncate">{{ $party['name'] }}</p>
                                    <span class="text-[10px] font-bold text-[#4f7cff] bg-[#4f7cff]/10 border border-[#4f7cff]/20 rounded-full px-2 py-0.5">{{ $party['type'] }}</span>
                                    @if($party['jurisdiction'])
                                    <span class="text-[10px] font-bold text-[#8b5cf6] bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 rounded-full px-2 py-0.5" title="{{ __('contracts.legal_jurisdiction') }}">{{ $party['jurisdiction'] }}</span>
                                    @endif
                                </div>
                                @if($party['signed'])
                                    <p class="text-[11px] text-[#00d9b5] inline-flex items-center gap-1 mt-1.5 font-semibold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                        {{ __('contracts.signed_on', ['date' => $party['signed_on']]) }}
                                    </p>
                                @else
                                    <p class="text-[11px] text-[#ffb020] inline-flex items-center gap-1 mt-1.5 font-semibold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        {{ __('contracts.awaiting_signature') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if($party['trn'] || $party['registration'] || $party['address'])
                        <div class="mt-3 pt-3 border-t border-[rgba(255,255,255,0.08)] grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                            @if($party['trn'])
                            <div>
                                <p class="text-[#b4b6c0] uppercase tracking-wider text-[10px]">{{ __('contracts.trn') }}</p>
                                <p class="font-mono font-semibold text-white">{{ $party['trn'] }}</p>
                            </div>
                            @endif
                            @if($party['registration'])
                            <div>
                                <p class="text-[#b4b6c0] uppercase tracking-wider text-[10px]">{{ __('contracts.registration_no') }}</p>
                                <p class="font-mono font-semibold text-white">{{ $party['registration'] }}</p>
                            </div>
                            @endif
                            @if($party['address'])
                            <div class="min-w-0">
                                <p class="text-[#b4b6c0] uppercase tracking-wider text-[10px]">{{ __('contracts.address') }}</p>
                                <p class="font-medium text-white truncate" title="{{ $party['address'] }}">{{ $party['address'] }}</p>
                            </div>
                            @endif
                        </div>
                        @endif

                        @if($party['signed'] && $party['sig_audit'])
                        <details class="mt-3 pt-3 border-t border-[rgba(255,255,255,0.08)]">
                            <summary class="cursor-pointer text-[11px] font-semibold text-[#b4b6c0] hover:text-white inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('contracts.audit_trail_show') }}
                            </summary>
                            <div class="mt-2 space-y-1 text-[11px]">
                                @if($party['sig_audit']['ip'])
                                <div class="flex items-start gap-2"><span class="text-[#b4b6c0] min-w-[80px]">IP:</span><span class="font-mono text-white">{{ $party['sig_audit']['ip'] }}</span></div>
                                @endif
                                @if($party['sig_audit']['user_agent'])
                                <div class="flex items-start gap-2"><span class="text-[#b4b6c0] min-w-[80px]">{{ __('contracts.device') }}:</span><span class="text-white text-[10px] break-all">{{ \Illuminate\Support\Str::limit($party['sig_audit']['user_agent'], 100) }}</span></div>
                                @endif
                                @if($party['sig_audit']['hash'])
                                <div class="flex items-start gap-2"><span class="text-[#b4b6c0] min-w-[80px]">{{ __('contracts.content_hash') }}:</span><span class="font-mono text-white text-[10px] break-all">{{ \Illuminate\Support\Str::limit($party['sig_audit']['hash'], 32) }}</span></div>
                                @endif
                            </div>
                        </details>
                        @endif
                    </div>
                    @endforeach
                </div>

                @if(!empty($contract['payment_schedule']))
                <div class="mb-6">
                    <x-payment-schedule
                        :rows="$contract['payment_schedule']"
                        :total="$contract['total_amount']"
                        title="Payment Schedule"
                        subtitle="Milestone breakdown for this contract." />
                </div>
                @endif

                <h3 class="text-[16px] font-semibold text-white mb-3">Contract Milestones</h3>
                <div class="space-y-3">
                    @forelse($contract['milestones'] as $m)
                    @php $mc = $milestoneColor[$m['status']] ?? $milestoneColor['future']; @endphp
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-full {{ $mc['bg'] }} flex items-center justify-center flex-shrink-0">
                                @if($m['status'] === 'paid')
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($m['status'] === 'pending')
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                @else
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-[14px] font-medium text-white">{{ $m['name'] }}</p>
                                <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $m['paid_date'] ?? $m['due_date'] ?? '—' }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center h-6 px-3 rounded-full {{ $mc['label_bg'] }} {{ $mc['label_text'] }} text-[12px] font-medium flex-shrink-0">
                            {{ $mc['label'] }}
                        </span>
                    </div>
                    @empty
                    <p class="text-[13px] text-[#b4b6c0] text-center py-6">No milestones defined.</p>
                    @endforelse
                </div>
            </div>

            {{-- Items tab — line-by-line breakdown of what is being
                 supplied. Cart-sourced contracts have multiple lines;
                 bid-driven and Buy-Now contracts get a single
                 synthesised line so the tab still renders cleanly. --}}
            <div x-show="tab === 'items'" x-cloak>
                <h3 class="text-[16px] font-semibold text-white mb-3">{{ __('contracts.line_items') }}</h3>
                <div class="overflow-x-auto -mx-[25px] px-[25px]">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-[rgba(255,255,255,0.1)]">
                                <th class="text-start text-[11px] font-medium text-[#b4b6c0] uppercase tracking-wider pb-3">{{ __('contracts.item_name') }}</th>
                                <th class="text-end text-[11px] font-medium text-[#b4b6c0] uppercase tracking-wider pb-3">{{ __('contracts.qty') }}</th>
                                <th class="text-end text-[11px] font-medium text-[#b4b6c0] uppercase tracking-wider pb-3">{{ __('contracts.unit_price') }}</th>
                                <th class="text-end text-[11px] font-medium text-[#b4b6c0] uppercase tracking-wider pb-3">{{ __('contracts.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contract['line_items'] as $item)
                            <tr class="border-b border-[rgba(255,255,255,0.05)]">
                                <td class="py-3">
                                    <p class="text-[14px] font-medium text-white">{{ $item['name'] }}</p>
                                    @if($item['sku'])
                                    <p class="text-[11px] text-[#b4b6c0] mt-0.5 font-mono">{{ $item['sku'] }}</p>
                                    @endif
                                </td>
                                <td class="py-3 text-end text-white">{{ $item['qty'] }}{{ $item['unit'] ? ' ' . $item['unit'] : '' }}</td>
                                <td class="py-3 text-end text-white">{{ $item['unit_price'] }}</td>
                                <td class="py-3 text-end text-[#00d9b5] font-semibold">{{ $item['total'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="pt-4 text-end text-[12px] text-[#b4b6c0]">{{ __('contracts.total_value') }}</td>
                                <td class="pt-4 text-end text-[18px] font-bold text-[#00d9b5]">{{ $contract['total_amount'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Tax + trade context — bid-snapshot fields that the
                     supplier supplied at bid time and the buyer locked
                     into the contract. Read-only here. --}}
                @php $amts = $contract['amounts_meta'] ?? null; @endphp
                @if(!empty($amts))
                <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @if(!empty($amts['tax_treatment']))
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-3">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.tax_treatment') }}</p>
                        <p class="text-[12px] font-semibold text-white">{{ ucfirst($amts['tax_treatment']) }}</p>
                    </div>
                    @endif
                    @if(!empty($amts['incoterm']))
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-3">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.incoterm') }}</p>
                        <p class="text-[12px] font-semibold text-white">{{ $amts['incoterm'] }}</p>
                    </div>
                    @endif
                    @if(!empty($amts['country_of_origin']))
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-3">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.country_of_origin') }}</p>
                        <p class="text-[12px] font-semibold text-white">{{ $amts['country_of_origin'] }}</p>
                    </div>
                    @endif
                    @if(!empty($amts['hs_code']))
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-3">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.hs_code') }}</p>
                        <p class="text-[12px] font-semibold text-white font-mono">{{ $amts['hs_code'] }}</p>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Payments tab — a chronological list of every Payment
                 row attached to this contract, with status, amount and
                 a link into the payment detail page (which has the tax
                 invoice download). --}}
            <div x-show="tab === 'payments'" x-cloak>
                <h3 class="text-[16px] font-semibold text-white mb-3">{{ __('contracts.payment_history') }}</h3>
                @forelse($contract['payments_history'] as $p)
                <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 mb-3 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-10 h-10 rounded-full {{ $p['is_paid'] ? 'bg-[rgba(0,217,181,0.15)]' : 'bg-[rgba(255,176,32,0.15)]' }} flex items-center justify-center flex-shrink-0">
                            @if($p['is_paid'])
                            <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @else
                            <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[14px] font-medium text-white">{{ $p['milestone'] }}</p>
                            <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $p['date'] }} · #{{ $p['id'] }}</p>
                        </div>
                    </div>
                    <div class="text-end flex items-center gap-3">
                        <span class="text-[16px] font-bold {{ $p['is_paid'] ? 'text-[#00d9b5]' : 'text-[#ffb020]' }}">{{ $p['amount'] }}</span>
                        <a href="{{ $p['invoice_url'] }}" class="inline-flex items-center h-9 px-3 rounded-[10px] text-[12px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                            {{ __('common.view_details') }}
                        </a>
                    </div>
                </div>
                @empty
                <p class="text-[13px] text-[#b4b6c0] text-center py-12">{{ __('contracts.no_payments_yet') }}</p>
                @endforelse

                {{-- Totals strip --}}
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.total_contract') }}</p>
                        <p class="text-[18px] font-bold text-white">{{ $contract['total_amount'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.received') }}</p>
                        <p class="text-[18px] font-bold text-[#00d9b5]">{{ $contract['paid_amount'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[11px] text-[#b4b6c0] mb-1">{{ __('contracts.pending') }}</p>
                        <p class="text-[18px] font-bold text-[#ffb020]">{{ $contract['pending_amount'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Terms tab — bilateral amendments + clause list + per-amendment
                 negotiation thread. --}}
            <div x-show="tab === 'terms'" x-cloak>
                @if($contract['can_amend'] || !empty($contract['amendments']))
                <div class="mb-6">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <h3 class="text-[16px] font-semibold text-white">{{ __('contracts.amendments') }}</h3>
                            <p class="text-[12px] text-[#b4b6c0] mt-1">{{ __('contracts.amendments_subtitle') }}</p>
                        </div>
                    </div>

                    @if($contract['can_amend'])
                    <div class="mb-4 p-3 rounded-[12px] bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.3)] text-[12px] text-[#4f7cff]">
                        {{ __('contracts.amendment_window_hint') }}
                    </div>
                    @else
                    <div class="mb-4 p-3 rounded-[12px] bg-[rgba(239,68,68,0.1)] border border-[rgba(239,68,68,0.3)] text-[12px] text-[#ef4444]">
                        {{ __('contracts.amendment_window_closed') }}
                    </div>
                    @endif

                    @if(empty($contract['amendments']))
                        <p class="text-[12px] text-[#b4b6c0]">{{ __('contracts.amendment_no_pending') }}</p>
                    @else
                    <div class="space-y-3">
                        @foreach($contract['amendments'] as $a)
                            @php
                                $statusClasses = match($a['status']) {
                                    'approved'         => 'text-[#00d9b5] bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)]',
                                    'rejected'         => 'text-[#ef4444] bg-[rgba(239,68,68,0.1)] border-[rgba(239,68,68,0.2)]',
                                    'pending_approval' => 'text-[#ffb020] bg-[rgba(255,176,32,0.1)] border-[rgba(255,176,32,0.2)]',
                                    default            => 'text-[#b4b6c0] bg-[#0f1117] border-[rgba(255,255,255,0.1)]',
                                };
                                $statusLabel = __('contracts.amendment_status_' . $a['status']);
                                $kindLabel   = $a['kind'] === 'add'
                                    ? __('contracts.amendment_kind_add')
                                    : __('contracts.amendment_kind_modify');
                            @endphp
                            <div id="amendment-card-{{ $a['id'] }}" class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                                <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] font-bold text-white bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-full px-2 py-0.5">{{ $kindLabel }}</span>
                                        <span class="text-[10px] font-bold rounded-full px-2 py-0.5 border {{ $statusClasses }}">{{ $statusLabel }}</span>
                                        <span class="text-[11px] text-[#b4b6c0]">{{ __('contracts.amendment_proposed_by', ['name' => $a['proposed_by']]) }} · {{ $a['proposed_at'] }}</span>
                                    </div>
                                </div>
                                <p class="text-[11px] text-[#b4b6c0] mb-2">{{ __('contracts.amendment_in_section', ['section' => $a['section_title']]) }}</p>

                                @if($a['kind'] === 'modify' && $a['old_text'])
                                <div class="mb-2">
                                    <p class="text-[10px] uppercase tracking-wider text-[#b4b6c0] mb-1">{{ __('contracts.amendment_old_text') }}</p>
                                    <p class="text-[12px] text-[#b4b6c0] line-through opacity-70">{{ $a['old_text'] }}</p>
                                </div>
                                @endif
                                <div class="mb-2">
                                    <p class="text-[10px] uppercase tracking-wider text-[#b4b6c0] mb-1">{{ __('contracts.amendment_new_text') }}</p>
                                    <p class="text-[12px] text-white font-medium">{{ $a['new_text'] }}</p>
                                </div>
                                @if($a['reason'])
                                <p class="text-[11px] text-[#b4b6c0] italic mt-2">"{{ $a['reason'] }}"</p>
                                @endif

                                @if($a['can_decide'])
                                <div class="mt-3 flex items-center gap-2 flex-wrap">
                                    <form method="POST" action="{{ route('dashboard.contracts.amendments.approve', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] text-[12px] font-bold text-white bg-[#00d9b5] hover:bg-[#00b894]">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                            {{ __('contracts.amendment_approve') }}
                                        </button>
                                    </form>
                                    <button type="button" @click="form = { kind: 'reject', amendmentId: {{ $a['id'] }} }" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] text-[12px] font-bold text-[#ef4444] bg-[rgba(239,68,68,0.1)] border border-[rgba(239,68,68,0.2)] hover:bg-[rgba(239,68,68,0.2)]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ __('contracts.amendment_reject') }}
                                    </button>
                                </div>

                                <form x-show="form && form.kind === 'reject' && form.amendmentId === {{ $a['id'] }}" x-cloak
                                      method="POST"
                                      action="{{ route('dashboard.contracts.amendments.reject', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}"
                                      onsubmit="return confirm('{{ __('contracts.amendment_confirm_reject') }}');"
                                      class="mt-3 p-3 bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] space-y-2">
                                    @csrf
                                    <label class="block text-[11px] text-[#b4b6c0]">{{ __('contracts.amendment_rejection_reason') }}</label>
                                    <textarea name="rejection_reason" rows="2" maxlength="500" class="w-full bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[8px] px-3 py-2 text-[12px] text-white focus:outline-none focus:border-[#4f7cff]/50"></textarea>
                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="px-3 py-1.5 rounded-[8px] text-[12px] font-bold text-white bg-[#ef4444] hover:bg-[#dc2626]">{{ __('contracts.amendment_reject') }}</button>
                                        <button type="button" @click="close()" class="px-3 py-1.5 rounded-[8px] text-[12px] font-medium text-[#b4b6c0] hover:text-white">{{ __('contracts.amendment_cancel') }}</button>
                                    </div>
                                </form>
                                @elseif($a['is_pending'] && $a['proposed_by_me'])
                                <p class="mt-3 text-[11px] text-[#b4b6c0] italic">{{ __('contracts.amendment_pending') }}</p>
                                @endif

                                {{-- Per-amendment negotiation thread --}}
                                @include('dashboard.contracts._amendment-thread', [
                                    'contract_id' => $contract['numeric_id'],
                                    'amendment'   => $a,
                                ])
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

                {{-- Terms list with inline propose/add buttons --}}
                <div class="space-y-5">
                    @forelse($contract['terms_sections'] as $i => $section)
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h4 class="text-[14px] font-semibold text-white">{{ ($i + 1) }}. {{ $section['title'] }}</h4>
                            @if($contract['can_amend'])
                            <button type="button"
                                    @click="open({ kind: 'add', sectionIndex: {{ $i }}, sectionTitle: @js($section['title']) })"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-[#4f7cff] hover:text-[#6b91ff]">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                                {{ __('contracts.amendment_add_clause') }}
                            </button>
                            @endif
                        </div>
                        <ul class="space-y-1 text-[13px] text-[#b4b6c0] ms-4">
                            @foreach($section['items'] as $j => $item)
                            <li class="group flex items-start gap-2">
                                <span>•</span>
                                <span class="flex-1">{{ $item }}</span>
                                @if($contract['can_amend'])
                                <button type="button"
                                        @click="open({ kind: 'modify', sectionIndex: {{ $i }}, itemIndex: {{ $j }}, oldText: @js($item), sectionTitle: @js($section['title']) })"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1 text-[10px] font-medium text-[#4f7cff] hover:text-[#6b91ff] flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                    {{ __('contracts.amendment_propose') }}
                                </button>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @empty
                    <p class="text-[13px] text-[#b4b6c0] text-center py-6">{{ __('common.no_data') }}</p>
                    @endforelse
                </div>

                {{-- Inline propose form (modify or add) --}}
                @if($contract['can_amend'])
                <form x-show="form && (form.kind === 'modify' || form.kind === 'add')" x-cloak
                      method="POST"
                      action="{{ route('dashboard.contracts.amendments.propose', ['id' => $contract['numeric_id']]) }}"
                      class="mt-5 p-4 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] space-y-3">
                    @csrf
                    <input type="hidden" name="kind" :value="form?.kind">
                    <input type="hidden" name="section_index" :value="form?.sectionIndex">
                    <input type="hidden" name="item_index" :value="form?.itemIndex ?? ''">

                    <p class="text-[11px] text-[#b4b6c0]" x-text="form?.kind === 'add'
                        ? '{{ __('contracts.amendment_add_clause_to', ['section' => '__SECTION__']) }}'.replace('__SECTION__', form?.sectionTitle || '')
                        : '{{ __('contracts.amendment_modify_clause') }}: ' + (form?.sectionTitle || '')"></p>

                    <template x-if="form?.kind === 'modify'">
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider text-[#b4b6c0] mb-1">{{ __('contracts.amendment_old_text') }}</label>
                            <p class="text-[12px] text-[#b4b6c0] italic line-through" x-text="form?.oldText"></p>
                        </div>
                    </template>

                    <div>
                        <label class="block text-[11px] uppercase tracking-wider text-[#b4b6c0] mb-1">{{ __('contracts.amendment_new_text') }}</label>
                        <textarea name="new_text" rows="3" maxlength="2000" required
                                  class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 py-2 text-[13px] text-white focus:outline-none focus:border-[#4f7cff]/50"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] uppercase tracking-wider text-[#b4b6c0] mb-1">{{ __('contracts.amendment_reason') }}</label>
                        <input type="text" name="reason" maxlength="500"
                               placeholder="{{ __('contracts.amendment_reason_placeholder') }}"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50">
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 rounded-[10px] text-[12px] font-bold text-white bg-[#4f7cff] hover:bg-[#6b91ff]">{{ __('contracts.amendment_submit') }}</button>
                        <button type="button" @click="close()" class="px-4 py-2 rounded-[10px] text-[12px] font-medium text-[#b4b6c0] hover:text-white">{{ __('contracts.amendment_cancel') }}</button>
                    </div>
                </form>
                @endif
            </div>

            <div x-show="tab === 'documents'" x-cloak>
                <div class="space-y-3 mb-5">
                    <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider">Contract Files</p>
                    @forelse($contract['documents'] as $doc)
                    <a href="{{ $doc['url'] ?? '#' }}" class="flex items-center gap-3 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 hover:border-[#4f7cff]/40 transition-colors">
                        <svg class="w-5 h-5 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <p class="text-[14px] font-medium text-white flex-1">{{ $doc['name'] }}</p>
                    </a>
                    @empty
                    <p class="text-[13px] text-[#b4b6c0] text-center py-4">No contract files.</p>
                    @endforelse
                </div>

                @if(!empty($contract['supplier_documents']))
                <div class="space-y-3">
                    <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider">Production Documents</p>
                    @foreach($contract['supplier_documents'] as $doc)
                    <a href="{{ $doc['url'] }}" class="flex items-center justify-between gap-3 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 hover:border-[#4f7cff]/40 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-[10px] bg-[rgba(0,217,181,0.1)] flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[14px] font-medium text-white truncate">{{ $doc['name'] }}</p>
                                <p class="text-[12px] text-[#b4b6c0]">{{ $doc['type'] }} · {{ $doc['size'] }} · {{ $doc['uploaded_at'] }}</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-[#b4b6c0] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        @if(!empty($contract['escrow']))
        <div class="mt-4">
            @include('dashboard.contracts._escrow-panel', ['escrow' => $contract['escrow'], 'contract_id' => $contract['numeric_id']])
        </div>
        @endif

        @if(!empty($contract['progress_log']))
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px] mt-4">
            <h3 class="text-[16px] font-semibold text-white mb-4">Progress Updates</h3>
            <div class="space-y-3">
                @foreach($contract['progress_log'] as $entry)
                <div class="flex items-start gap-3 pb-3 border-b border-[rgba(255,255,255,0.06)] last:border-b-0 last:pb-0">
                    <div class="w-8 h-8 rounded-full bg-[rgba(0,217,181,0.15)] flex items-center justify-center flex-shrink-0 text-[11px] font-semibold text-[#00d9b5]">{{ $entry['percent'] }}%</div>
                    <div class="flex-1 min-w-0">
                        @if($entry['note'])
                        <p class="text-[14px] text-white leading-[20px]">{{ $entry['note'] }}</p>
                        @else
                        <p class="text-[14px] text-[#b4b6c0] italic">Progress updated to {{ $entry['percent'] }}%</p>
                        @endif
                        <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $entry['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($contract['can_review'])
        <div class="mt-4">
            <x-contract-review :contract-id="$contract['numeric_id']" :existing="$contract['existing_review']" />
        </div>
        @endif
    </div>

    {{-- RIGHT: supplier-side action panels --}}
    <div class="space-y-4">
        {{-- Quick Actions --}}
        <div x-data="{ open: null }" class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Quick Actions</h3>
            <div class="space-y-3">
                {{-- Sign / Upload signature CTA --}}
                @if($contract['can_sign'])
                    @if($contract['needs_signature_assets'])
                    <button type="button" @click="$dispatch('open-signature-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        {{ __('contracts.upload_signature_cta') }}
                    </button>
                    @else
                    <button type="button" @click="$dispatch('open-sign-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
                        {{ __('contracts.sign_contract') }}
                    </button>
                    @endif
                @endif

                @if(!empty($contract['can_decline']))
                <button type="button" @click="$dispatch('open-decline-modal')"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[13px] font-semibold text-[#ef4444] bg-[rgba(239,68,68,0.08)] border border-[rgba(239,68,68,0.3)] hover:bg-[rgba(239,68,68,0.15)] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ __('contracts.decline_contract') }}
                </button>
                @endif

                @if(!empty($contract['can_terminate']))
                <button type="button" @click="$dispatch('open-terminate-modal')"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[13px] font-semibold text-[#ef4444] bg-[rgba(239,68,68,0.08)] border border-[rgba(239,68,68,0.3)] hover:bg-[rgba(239,68,68,0.15)] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    {{ __('contracts.terminate_contract') }}
                </button>
                @endif

                {{-- Update Progress --}}
                <button type="button" @click="open = open === 'progress' ? null : 'progress'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5v9"/></svg>
                    Update Progress
                </button>
                <form x-show="open === 'progress'" x-cloak method="POST"
                      action="{{ route('dashboard.contracts.progress', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Progress (%)</label>
                        <input type="number" name="progress_percentage" min="0" max="100" required
                               value="{{ $contract['progress'] }}"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Note (optional)</label>
                        <textarea name="note" rows="2" placeholder="What changed?"
                                  class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 py-2 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">Save</button>
                </form>

                {{-- Upload Documents --}}
                <button type="button" @click="open = open === 'documents' ? null : 'documents'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    Upload Documents
                </button>
                <form x-show="open === 'documents'" x-cloak method="POST" enctype="multipart/form-data"
                      action="{{ route('dashboard.contracts.documents.upload', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <input type="file" name="documents[]" multiple required
                           class="block w-full text-[12px] text-[#b4b6c0] file:bg-[#4f7cff] file:text-white file:border-0 file:rounded-[8px] file:px-3 file:py-2 file:me-3 file:cursor-pointer file:text-[12px]">
                    <p class="text-[11px] text-[#b4b6c0]">PDF, DOC, XLS, Images. Max 10MB each, 10 files.</p>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">Upload</button>
                </form>

                {{-- Schedule Shipment --}}
                <button type="button" @click="open = open === 'shipment' ? null : 'shipment'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    Schedule Shipment
                </button>
                <form x-show="open === 'shipment'" x-cloak method="POST"
                      action="{{ route('dashboard.contracts.shipments.schedule', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Tracking Number</label>
                        <input type="text" name="tracking_number" placeholder="auto-generated if blank"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Carrier</label>
                        <input type="text" name="carrier" placeholder="DHL, Aramex, FedEx..."
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="origin" placeholder="Origin"
                               class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                        <input type="text" name="destination" placeholder="Destination"
                               class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Estimated Delivery</label>
                        <input type="date" name="estimated_delivery"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">Schedule</button>
                </form>
            </div>
        </div>

        {{-- Signature & stamp on file --}}
        @if($contract['signature_assets']['has_both'])
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[14px] font-bold text-white">{{ __('contracts.signature_label') }}</h3>
                <button type="button" @click="$dispatch('open-signature-modal')" class="text-[11px] font-semibold text-[#4f7cff] hover:text-[#6b91ff]">{{ __('contracts.signature_replace') }}</button>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['signature_url'] }}" alt="signature" class="max-h-12 w-auto">
                </div>
                <div class="flex-1 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[10px] p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['stamp_url'] }}" alt="stamp" class="max-h-12 w-auto">
                </div>
            </div>
        </div>
        @endif

        {{-- Payment Summary --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">{{ __('contracts.payment_summary') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.total_contract') }}</dt>
                    <dd class="text-white font-medium">{{ $contract['total_amount'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.received') }}</dt>
                    <dd class="text-[#00d9b5] font-semibold">{{ $contract['paid_amount'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.pending') }}</dt>
                    <dd class="text-[#ffb020] font-semibold">{{ $contract['pending_amount'] }}</dd>
                </div>
            </dl>
            <div class="mt-4 pt-4 border-t border-[rgba(255,255,255,0.1)]">
                <div class="flex items-center justify-between text-[12px] mb-2">
                    <span class="text-[#b4b6c0]">{{ __('common.progress') }}</span>
                    <span class="text-white font-medium">{{ $contract['progress'] }}%</span>
                </div>
                <div class="w-full h-1.5 bg-[#252932] rounded-full overflow-hidden">
                    <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $contract['progress'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Buyer Contact --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Buyer Contact</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Company</dt>
                    <dd class="text-white font-medium">{{ $contract['buyer_contact']['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Email</dt>
                    <dd class="text-white font-medium break-all">{{ $contract['buyer_contact']['email'] }}</dd>
                </div>
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Phone</dt>
                    <dd class="text-white font-medium">{{ $contract['buyer_contact']['phone'] }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

{{-- Signature & stamp upload modal --}}
@include('dashboard.contracts._signature-modal', [
    'contract_id'      => $contract['numeric_id'],
    'signature_assets' => $contract['signature_assets'],
    'open'             => $contract['needs_signature_assets'],
])

{{-- Sign-contract confirmation modal — replaces the legacy
     window.confirm() dialog with a step-up password + consent flow. --}}
@if($contract['can_sign'] && !$contract['needs_signature_assets'])
@include('dashboard.contracts._sign-modal', [
    'contract'             => $contract,
    'signing_company_name' => auth()->user()?->company?->name ?? '—',
])
@endif

{{-- Decline / Terminate reason modals --}}
@if(!empty($contract['can_decline']))
@include('dashboard.contracts._reason-modal', [
    'event_name'  => 'open-decline-modal',
    'title'       => __('contracts.decline_modal_title'),
    'subtitle'    => __('contracts.decline_modal_subtitle'),
    'action_url'  => route('dashboard.contracts.decline', ['id' => $contract['numeric_id']]),
    'button_label'=> __('contracts.decline_contract'),
    'button_class'=> 'bg-[#ef4444] hover:bg-[#dc2626]',
    'min_length'  => 5,
])
@endif

@if(!empty($contract['can_terminate']))
@include('dashboard.contracts._reason-modal', [
    'event_name'  => 'open-terminate-modal',
    'title'       => __('contracts.terminate_modal_title'),
    'subtitle'    => __('contracts.terminate_modal_subtitle'),
    'action_url'  => route('dashboard.contracts.terminate', ['id' => $contract['numeric_id']]),
    'button_label'=> __('contracts.terminate_contract'),
    'button_class'=> 'bg-[#ef4444] hover:bg-[#dc2626]',
    'min_length'  => 10,
])
@endif

@endsection
