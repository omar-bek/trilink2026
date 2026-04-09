@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.details'))

@section('content')

{{-- Flash messages: success status + validation errors raised by sign /
     amendment / signature-upload endpoints. Without this banner the
     forms appeared silent on failure. --}}
@if(session('status'))
<div class="mb-4 p-4 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-medium">
    {{ session('status') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 p-4 rounded-xl bg-[#ef4444]/10 border border-[#ef4444]/30 text-[13px] text-[#ef4444] font-medium">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div class="min-w-0">
        <a href="{{ route('dashboard.contracts') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back_to_dashboard') }}
        </a>
        <div class="flex items-center gap-3 flex-wrap mb-2">
            <p class="text-[12px] font-mono text-muted">{{ $contract['id'] }}</p>
            <x-dashboard.status-badge :status="$contract['status']" />
        </div>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ $contract['title'] }}</h1>
        <p class="text-[13px] text-muted mt-1">{{ __('contracts.details') }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'ar']) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            {{ __('contracts.download_ar') }}
        </a>
        <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'en']) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            {{ __('contracts.download_en') }}
        </a>
    </div>
</div>

{{-- Pre-signature alert: tells the buyer the contract is ready for them
     to sign and (when missing) prompts them to upload signature/stamp
     before they can proceed. --}}
@if($contract['status'] === 'pending')
<div class="mb-6 bg-gradient-to-r from-accent/10 to-[#00d9b5]/10 border border-accent/30 rounded-2xl p-5 flex items-start justify-between gap-4 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-accent/15 text-accent flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
        </div>
        <div class="min-w-0">
            <p class="text-[15px] font-bold text-primary">{{ __('contracts.created_from_bid') }}</p>
            <p class="text-[12px] text-muted mt-0.5">{{ __('contracts.amendment_window_hint') }}</p>
        </div>
    </div>
    @if($contract['can_sign'])
        @if($contract['needs_signature_assets'])
            <button type="button" @click="$dispatch('open-signature-modal')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                {{ __('contracts.upload_signature_cta') }}
            </button>
        @else
            <button type="button" @click="$dispatch('open-sign-modal')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00b894] shadow-[0_4px_14px_rgba(0,217,181,0.25)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
                {{ __('contracts.sign_contract') }}
            </button>
        @endif
    @endif
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Contract Status (KPIs) --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.contract_status') }}</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('contracts.total_value') }}</p>
                    <p class="text-[20px] font-bold text-[#00d9b5] leading-none">{{ $contract['amount'] }}</p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-2">{{ __('common.progress') }}</p>
                    <div class="flex items-center gap-2">
                        <p class="text-[20px] font-bold text-primary leading-none">{{ $contract['progress'] }}%</p>
                        <div class="flex-1 h-1.5 bg-elevated rounded-full overflow-hidden"><div class="h-full bg-accent rounded-full" style="width: {{ $contract['progress'] }}%"></div></div>
                    </div>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('contracts.days_remaining') }}</p>
                    <p class="text-[20px] font-bold text-primary leading-none">{{ $contract['days_remaining'] ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Parties Involved with explicit signature progress --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.parties') }}</h3>
            <div class="space-y-3">
                @forelse($contract['parties'] as $party)
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <div class="flex items-start gap-4">
                        <div class="w-11 h-11 rounded-xl {{ $party['color'] }} text-white font-bold flex items-center justify-center flex-shrink-0" aria-hidden="true">{{ $party['code'] }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <p class="text-[14px] font-bold text-primary truncate">{{ $party['name'] }}</p>
                                <span class="text-[10px] font-bold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ $party['type'] }}</span>
                                @if($party['jurisdiction'])
                                <span class="text-[10px] font-bold text-[#8b5cf6] bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 rounded-full px-2 py-0.5" title="{{ __('contracts.legal_jurisdiction') }}">{{ $party['jurisdiction'] }}</span>
                                @endif
                            </div>
                            @if($party['contact'])
                                <p class="text-[12px] text-muted truncate">{{ $party['contact'] }}</p>
                            @endif
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

                    {{-- Legal identity strip — TRN, registration #, address.
                         Surfaces the data the auditor / counterparty would
                         otherwise have to drill into the company profile to
                         confirm. Hidden when none of the fields are set so
                         legacy companies don't render an empty box. --}}
                    @if($party['trn'] || $party['registration'] || $party['address'])
                    <div class="mt-3 pt-3 border-t border-th-border grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                        @if($party['trn'])
                        <div>
                            <p class="text-muted uppercase tracking-wider text-[10px]">{{ __('contracts.trn') }}</p>
                            <p class="font-mono font-semibold text-primary">{{ $party['trn'] }}</p>
                        </div>
                        @endif
                        @if($party['registration'])
                        <div>
                            <p class="text-muted uppercase tracking-wider text-[10px]">{{ __('contracts.registration_no') }}</p>
                            <p class="font-mono font-semibold text-primary">{{ $party['registration'] }}</p>
                        </div>
                        @endif
                        @if($party['address'])
                        <div class="min-w-0">
                            <p class="text-muted uppercase tracking-wider text-[10px]">{{ __('contracts.address') }}</p>
                            <p class="font-medium text-primary truncate" title="{{ $party['address'] }}">{{ $party['address'] }}</p>
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- E-signature audit trail — appears only after the
                         party has signed. UAE Federal Decree-Law 46/2021
                         Article 18 evidentiary requirements: IP, device,
                         and the contract-content hash at sign time. --}}
                    @if($party['signed'] && $party['sig_audit'])
                    <details class="mt-3 pt-3 border-t border-th-border">
                        <summary class="cursor-pointer text-[11px] font-semibold text-muted hover:text-primary inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('contracts.audit_trail_show') }}
                        </summary>
                        <div class="mt-2 space-y-1 text-[11px]">
                            @if($party['sig_audit']['ip'])
                            <div class="flex items-start gap-2"><span class="text-muted min-w-[80px]">IP:</span><span class="font-mono text-primary">{{ $party['sig_audit']['ip'] }}</span></div>
                            @endif
                            @if($party['sig_audit']['user_agent'])
                            <div class="flex items-start gap-2"><span class="text-muted min-w-[80px]">{{ __('contracts.device') }}:</span><span class="text-primary text-[10px] break-all">{{ \Illuminate\Support\Str::limit($party['sig_audit']['user_agent'], 100) }}</span></div>
                            @endif
                            @if($party['sig_audit']['hash'])
                            <div class="flex items-start gap-2"><span class="text-muted min-w-[80px]">{{ __('contracts.content_hash') }}:</span><span class="font-mono text-primary text-[10px] break-all">{{ \Illuminate\Support\Str::limit($party['sig_audit']['hash'], 32) }}</span></div>
                            @endif
                        </div>
                    </details>
                    @endif
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Payment Schedule (milestone breakdown component) --}}
        @if(!empty($contract['payment_schedule']))
        <x-payment-schedule
            :rows="$contract['payment_schedule']"
            :total="$contract['amount']"
            title="{{ __('contracts.payment_schedule') ?? 'Payment Schedule' }}"
            subtitle="{{ __('contracts.payment_schedule_hint') ?? 'Milestone breakdown for this contract.' }}" />
        @endif

        {{-- Payment Milestones (status + actions per milestone) --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.payment_milestones') }}</h3>
            <div class="space-y-3">
                @forelse($contract['milestones'] as $milestone)
                    @php
                        $status = $milestone['status'];
                        $wrapClasses = match($status) {
                            'paid'    => 'bg-[#00d9b5]/5 border border-[#00d9b5]/20',
                            'pending' => 'bg-[#ffb020]/5 border border-[#ffb020]/20',
                            default   => 'bg-page border border-th-border',
                        };
                        $iconBg = match($status) {
                            'paid'    => 'bg-[#00d9b5]/20',
                            'pending' => 'bg-[#ffb020]/20',
                            default   => 'bg-surface-2',
                        };
                        $badgeClasses = match($status) {
                            'paid'    => 'text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20',
                            'pending' => 'text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20',
                            default   => 'text-muted bg-surface-2 border border-th-border',
                        };
                    @endphp
                    <div class="{{ $wrapClasses }} rounded-xl p-5 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg {{ $iconBg }} flex items-center justify-center flex-shrink-0">
                            @if($status === 'paid')
                                <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($status === 'pending')
                                <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            @else
                                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <p class="text-[14px] font-bold text-primary">{{ $milestone['name'] }}</p>
                                <span class="text-[10px] font-bold {{ $badgeClasses }} rounded-full px-2 py-0.5">{{ $milestone['percentage'] }}%</span>
                            </div>
                            <p class="text-[11px] text-muted">
                                @if($status === 'paid' && $milestone['paid_date'])
                                    {{ __('common.due_date') }}: {{ $milestone['due_date'] }} · {{ __('contracts.paid_on', ['date' => $milestone['paid_date']]) }}
                                @else
                                    {{ __('common.due_date') }}: {{ $milestone['due_date'] ?: '—' }}
                                @endif
                            </p>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <p class="text-[18px] font-bold text-accent">{{ $milestone['amount'] }}</p>
                            @if($status === 'pending' && $milestone['payment_id'])
                                @can('payment.process')
                                <form method="POST" action="{{ route('dashboard.payments.process', ['id' => $milestone['payment_id']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="mt-1 px-3 py-1 rounded-lg text-[11px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('contracts.process_payment') }}</button>
                                </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Terms & Conditions + bilateral Amendments + Negotiation threads.
             All inside the same Alpine x-data island so the inline propose
             form coordinates with whichever clause the user is editing. --}}
        <div x-data="{ form: null, open(f) { this.form = f; }, close() { this.form = null; } }">

            {{-- ============== AMENDMENTS PANEL ============== --}}
            @if($contract['can_amend'] || !empty($contract['amendments']))
            <div class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.amendments') }}</h3>
                        <p class="text-[12px] text-muted mt-1">{{ __('contracts.amendments_subtitle') }}</p>
                    </div>
                </div>

                @if($contract['can_amend'])
                <div class="mb-4 p-3 rounded-xl bg-accent/10 border border-accent/30 text-[12px] text-accent">
                    {{ __('contracts.amendment_window_hint') }}
                </div>
                @else
                <div class="mb-4 p-3 rounded-xl bg-[#ef4444]/10 border border-[#ef4444]/30 text-[12px] text-[#ef4444]">
                    {{ __('contracts.amendment_window_closed') }}
                </div>
                @endif

                @if(empty($contract['amendments']))
                    <p class="text-[12px] text-muted">{{ __('contracts.amendment_no_pending') }}</p>
                @else
                <div class="space-y-3">
                    @foreach($contract['amendments'] as $a)
                        @php
                            $statusClasses = match($a['status']) {
                                'approved'         => 'text-[#00d9b5] bg-[#00d9b5]/10 border-[#00d9b5]/20',
                                'rejected'         => 'text-[#ef4444] bg-[#ef4444]/10 border-[#ef4444]/20',
                                'pending_approval' => 'text-[#ffb020] bg-[#ffb020]/10 border-[#ffb020]/20',
                                default            => 'text-muted bg-surface-2 border-th-border',
                            };
                            $statusLabel = __('contracts.amendment_status_' . $a['status']);
                            $kindLabel   = $a['kind'] === 'add'
                                ? __('contracts.amendment_kind_add')
                                : __('contracts.amendment_kind_modify');
                        @endphp
                        <div id="amendment-card-{{ $a['id'] }}" class="bg-page border border-th-border rounded-xl p-4">
                            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-bold text-primary bg-surface-2 border border-th-border rounded-full px-2 py-0.5">{{ $kindLabel }}</span>
                                    <span class="text-[10px] font-bold rounded-full px-2 py-0.5 border {{ $statusClasses }}">{{ $statusLabel }}</span>
                                    <span class="text-[11px] text-muted">{{ __('contracts.amendment_proposed_by', ['name' => $a['proposed_by']]) }} · {{ $a['proposed_at'] }}</span>
                                </div>
                            </div>
                            <p class="text-[11px] text-muted mb-2">{{ __('contracts.amendment_in_section', ['section' => $a['section_title']]) }}</p>

                            @if($a['kind'] === 'modify' && $a['old_text'])
                            <div class="mb-2">
                                <p class="text-[10px] uppercase tracking-wider text-muted mb-1">{{ __('contracts.amendment_old_text') }}</p>
                                <p class="text-[12px] text-body line-through opacity-70">{{ $a['old_text'] }}</p>
                            </div>
                            @endif
                            <div class="mb-2">
                                <p class="text-[10px] uppercase tracking-wider text-muted mb-1">{{ __('contracts.amendment_new_text') }}</p>
                                <p class="text-[12px] text-primary font-medium">{{ $a['new_text'] }}</p>
                            </div>
                            @if($a['reason'])
                            <p class="text-[11px] text-muted italic mt-2">"{{ $a['reason'] }}"</p>
                            @endif

                            @if($a['can_decide'])
                            <div class="mt-3 flex items-center gap-2 flex-wrap">
                                <form method="POST" action="{{ route('dashboard.contracts.amendments.approve', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-bold text-white bg-[#00d9b5] hover:bg-[#00b894]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                        {{ __('contracts.amendment_approve') }}
                                    </button>
                                </form>
                                <button type="button" @click="open({ kind: 'reject', amendmentId: {{ $a['id'] }} })" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-bold text-[#ef4444] bg-[#ef4444]/10 border border-[#ef4444]/20 hover:bg-[#ef4444]/20">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ __('contracts.amendment_reject') }}
                                </button>
                            </div>

                            <form x-show="form && form.kind === 'reject' && form.amendmentId === {{ $a['id'] }}" x-cloak
                                  method="POST"
                                  action="{{ route('dashboard.contracts.amendments.reject', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}"
                                  onsubmit="return confirm('{{ __('contracts.amendment_confirm_reject') }}');"
                                  class="mt-3 p-3 bg-surface border border-th-border rounded-lg space-y-2">
                                @csrf
                                <label class="block text-[11px] text-muted">{{ __('contracts.amendment_rejection_reason') }}</label>
                                <textarea name="rejection_reason" rows="2" maxlength="500" class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/50"></textarea>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg text-[12px] font-bold text-white bg-[#ef4444] hover:bg-[#dc2626]">{{ __('contracts.amendment_reject') }}</button>
                                    <button type="button" @click="close()" class="px-3 py-1.5 rounded-lg text-[12px] font-medium text-muted hover:text-primary">{{ __('contracts.amendment_cancel') }}</button>
                                </div>
                            </form>
                            @elseif($a['is_pending'] && $a['proposed_by_me'])
                            <p class="mt-3 text-[11px] text-muted italic">{{ __('contracts.amendment_pending') }}</p>
                            @endif

                            {{-- Per-amendment negotiation/discussion thread --}}
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

            {{-- ============== TERMS & CONDITIONS ============== --}}
            <div class="bg-surface border border-th-border rounded-2xl p-6">
                <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.terms_conditions') }}</h3>
                <div class="space-y-5">
                    @forelse($contract['terms_sections'] as $i => $section)
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h4 class="text-[14px] font-bold text-primary">{{ ($i + 1) }}. {{ $section['title'] }}</h4>
                            @if($contract['can_amend'])
                            <button type="button"
                                    @click="open({ kind: 'add', sectionIndex: {{ $i }}, sectionTitle: @js($section['title']) })"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-accent hover:text-accent-h">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                                {{ __('contracts.amendment_add_clause') }}
                            </button>
                            @endif
                        </div>
                        <ul class="space-y-1 text-[13px] text-body ms-4">
                            @foreach($section['items'] as $j => $item)
                            <li class="group flex items-start gap-2">
                                <span>•</span>
                                <span class="flex-1">{{ $item }}</span>
                                @if($contract['can_amend'])
                                <button type="button"
                                        @click="open({ kind: 'modify', sectionIndex: {{ $i }}, itemIndex: {{ $j }}, oldText: @js($item), sectionTitle: @js($section['title']) })"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1 text-[10px] font-medium text-accent hover:text-accent-h flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                    {{ __('contracts.amendment_propose') }}
                                </button>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @empty
                    <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                    @endforelse
                </div>

                {{-- Inline propose form (modify or add) --}}
                @if($contract['can_amend'])
                <form x-show="form && (form.kind === 'modify' || form.kind === 'add')" x-cloak
                      method="POST"
                      action="{{ route('dashboard.contracts.amendments.propose', ['id' => $contract['numeric_id']]) }}"
                      class="mt-5 p-4 bg-page border border-th-border rounded-xl space-y-3">
                    @csrf
                    <input type="hidden" name="kind" :value="form?.kind">
                    <input type="hidden" name="section_index" :value="form?.sectionIndex">
                    <input type="hidden" name="item_index" :value="form?.itemIndex ?? ''">

                    <div>
                        <p class="text-[11px] text-muted mb-1" x-text="form?.kind === 'add'
                            ? '{{ __('contracts.amendment_add_clause_to', ['section' => '__SECTION__']) }}'.replace('__SECTION__', form?.sectionTitle || '')
                            : '{{ __('contracts.amendment_modify_clause') }}: ' + (form?.sectionTitle || '')"></p>
                    </div>

                    <template x-if="form?.kind === 'modify'">
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider text-muted mb-1">{{ __('contracts.amendment_old_text') }}</label>
                            <p class="text-[12px] text-muted italic line-through" x-text="form?.oldText"></p>
                        </div>
                    </template>

                    <div>
                        <label class="block text-[11px] uppercase tracking-wider text-muted mb-1">{{ __('contracts.amendment_new_text') }}</label>
                        <textarea name="new_text" rows="3" maxlength="2000" required
                                  class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/50"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] uppercase tracking-wider text-muted mb-1">{{ __('contracts.amendment_reason') }}</label>
                        <input type="text" name="reason" maxlength="500"
                               placeholder="{{ __('contracts.amendment_reason_placeholder') }}"
                               class="w-full bg-surface border border-th-border rounded-lg px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent/50">
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('contracts.amendment_submit') }}</button>
                        <button type="button" @click="close()" class="px-4 py-2 rounded-lg text-[12px] font-medium text-muted hover:text-primary">{{ __('contracts.amendment_cancel') }}</button>
                    </div>
                </form>
                @endif

                <div class="grid grid-cols-2 gap-2 mt-6">
                    <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'ar']) }}" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ __('contracts.download_ar') }}
                    </a>
                    <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'en']) }}" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ __('contracts.download_en') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">

        {{-- Phase 3 — Escrow panel --}}
        @if(!empty($contract['escrow']))
        @include('dashboard.contracts._escrow-panel', ['escrow' => $contract['escrow'], 'contract_id' => $contract['numeric_id']])
        @endif

        {{-- Quick Actions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.quick_actions') }}</h3>
            <div class="space-y-3">
                @if($contract['can_sign'])
                    @if($contract['needs_signature_assets'])
                    <button type="button" @click="$dispatch('open-signature-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        {{ __('contracts.upload_signature_cta') }}
                    </button>
                    @else
                    <button type="button" @click="$dispatch('open-sign-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00b894] shadow-[0_4px_14px_rgba(0,217,181,0.25)]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                        </svg>
                        {{ __('contracts.sign_contract') }}
                    </button>
                    @endif
                @endif

                @if(!empty($contract['can_decline']))
                    <button type="button" @click="$dispatch('open-decline-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-[#ef4444] bg-[#ef4444]/5 border border-[#ef4444]/30 hover:bg-[#ef4444]/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('contracts.decline_contract') }}
                    </button>
                @endif

                @if(!empty($contract['can_terminate']))
                    <button type="button" @click="$dispatch('open-terminate-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-[#ef4444] bg-[#ef4444]/5 border border-[#ef4444]/30 hover:bg-[#ef4444]/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        {{ __('contracts.terminate_contract') }}
                    </button>
                @endif

                @if($contract['has_shipment'] && $contract['shipment_id'])
                <a href="{{ route('dashboard.shipments.show', ['id' => $contract['shipment_id']]) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    {{ __('contracts.track_shipment') }}
                </a>
                @endif
                <a href="{{ route('dashboard.disputes') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                    {{ __('contracts.report_issue') }}
                </a>

                @if(auth()->user()?->hasPermission('cart.use') && auth()->user()?->company_id === ($contract['buyer_company_id'] ?? null))
                <form method="POST" action="{{ route('dashboard.contracts.reorder', ['id' => $contract['numeric_id']]) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        {{ __('cart.buy_again') }}
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Signature & stamp on file --}}
        @if($contract['signature_assets']['has_both'])
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[14px] font-bold text-primary">{{ __('contracts.signature_label') }}</h3>
                <button type="button" @click="$dispatch('open-signature-modal')" class="text-[11px] font-semibold text-accent hover:text-accent-h">{{ __('contracts.signature_replace') }}</button>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-page border border-th-border rounded-lg p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['signature_url'] }}" alt="signature" class="max-h-12 w-auto">
                </div>
                <div class="flex-1 bg-page border border-th-border rounded-lg p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['stamp_url'] }}" alt="stamp" class="max-h-12 w-auto">
                </div>
            </div>
        </div>
        @endif

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-5">{{ __('contracts.timeline') }}</h3>
            <div class="space-y-5">
                @forelse($contract['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)<div class="absolute start-[5px] top-3 w-0.5 h-full bg-th-border"></div>@endif
                    <div class="w-2.5 h-2.5 rounded-full {{ $event['done'] ? 'bg-[#00d9b5]' : 'bg-th-border' }} mt-1.5 flex-shrink-0 z-10"></div>
                    <div class="flex-1 min-w-0 pb-2">
                        <p class="text-[10px] text-muted">{{ $event['date'] }}</p>
                        <p class="text-[13px] font-bold text-primary">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted leading-snug">{{ $event['desc'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Documents --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.documents') }}</h3>
            <div class="space-y-2">
                @forelse($contract['documents'] as $file)
                <div class="bg-page border border-th-border rounded-lg p-3 flex items-center gap-3">
                    <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    <span class="text-[12px] font-medium text-body flex-1 truncate">{{ $file['name'] }}</span>
                    @if($file['url'])
                        <a href="{{ $file['url'] }}" class="w-6 h-6 rounded text-muted hover:text-primary flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg></a>
                    @endif
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        @if(!empty($contract['supplier_documents']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.supplier_documents') ?? 'Supplier Documents' }}</h3>
            <div class="space-y-2">
                @foreach($contract['supplier_documents'] as $doc)
                <a href="{{ $doc['url'] }}" class="bg-page border border-th-border rounded-lg p-3 flex items-center gap-3 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded bg-[#00d9b5]/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-medium text-primary truncate">{{ $doc['name'] }}</p>
                        <p class="text-[11px] text-muted">{{ $doc['type'] }} · {{ $doc['size'] }} · {{ $doc['uploaded_at'] }}</p>
                    </div>
                    <svg class="w-3.5 h-3.5 text-muted flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($contract['progress_log']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.progress_updates') ?? 'Progress Updates' }}</h3>
            <div class="space-y-3">
                @foreach($contract['progress_log'] as $entry)
                <div class="flex items-start gap-3 pb-3 border-b border-th-border last:border-b-0 last:pb-0">
                    <div class="w-8 h-8 rounded-full bg-[#00d9b5]/15 flex items-center justify-center flex-shrink-0 text-[11px] font-semibold text-[#00d9b5]">{{ $entry['percent'] }}%</div>
                    <div class="flex-1 min-w-0">
                        @if($entry['note'])
                        <p class="text-[12px] text-primary leading-[18px]">{{ $entry['note'] }}</p>
                        @else
                        <p class="text-[12px] text-muted italic">Progress updated to {{ $entry['percent'] }}%</p>
                        @endif
                        <p class="text-[11px] text-muted mt-0.5">{{ $entry['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Rate this contract (only after completion) --}}
@if($contract['can_review'])
<div class="mt-6">
    <x-contract-review :contract-id="$contract['numeric_id']" :existing="$contract['existing_review']" />
</div>
@endif

{{-- Signature & stamp upload modal — auto-opens when the buyer is about
     to sign but hasn't uploaded their assets yet. Either CTA above also
     dispatches `open-signature-modal` to surface it on demand. --}}
@include('dashboard.contracts._signature-modal', [
    'contract_id'      => $contract['numeric_id'],
    'signature_assets' => $contract['signature_assets'],
    'open'             => $contract['needs_signature_assets'],
])

{{-- Sign-contract confirmation modal — replaces the legacy
     window.confirm() dialog. Wires step-up password + consent
     checkbox + IP/UA capture to satisfy Federal Decree-Law 46/2021. --}}
@if($contract['can_sign'] && !$contract['needs_signature_assets'])
@include('dashboard.contracts._sign-modal', [
    'contract'             => $contract,
    'signing_company_name' => auth()->user()?->company?->name ?? '—',
])
@endif

{{-- Decline / Terminate reason modals — destructive actions that
     require a written justification before submission. --}}
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
