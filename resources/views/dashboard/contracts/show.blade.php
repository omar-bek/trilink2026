@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.details'))

@section('content')

{{-- ============================================================
     CONTRACT DETAILS — Unified buyer + supplier view
     Design language: Trilink dashboard tokens (theme-aware
     bg-surface / bg-page / text-primary + Trilink palette
     #4f7cff / #00d9b5 / #ffb020 / #ff4d7f / #8b5cf6).
     ============================================================ --}}

{{-- Flash messages --}}
@if(session('status'))
<div class="mb-4 p-4 rounded-[12px] bg-accent-success/10 border border-accent-success/30 text-[13px] text-accent-success font-medium flex items-start gap-3">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>{{ session('status') }}</span>
</div>
@endif
@if($errors->any())
<div class="mb-4 p-4 rounded-[12px] bg-accent-danger/10 border border-accent-danger/30 text-[13px] text-accent-danger font-medium">
    @foreach($errors->all() as $error)
        <div class="flex items-start gap-2"><span aria-hidden="true">•</span><span>{{ $error }}</span></div>
    @endforeach
</div>
@endif

{{-- Breadcrumb --}}
<nav class="mb-3" aria-label="Breadcrumb">
    <ol class="flex items-center gap-1.5 text-[12px] text-muted flex-wrap">
        <li><a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">{{ __('common.dashboard') ?? 'Dashboard' }}</a></li>
        <li aria-hidden="true">
            <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </li>
        <li><a href="{{ route('dashboard.contracts') }}" class="hover:text-primary transition-colors">{{ __('contracts.title') }}</a></li>
        <li aria-hidden="true">
            <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </li>
        <li aria-current="page" class="text-primary font-mono">{{ $contract['id'] }}</li>
    </ol>
</nav>

{{-- ============================================================
     HERO HEADER — One unified card with id/status/direction,
     title, the 4 core KPIs, and download CTAs. The decorative
     accent bar on the start edge mirrors the index row design.
     ============================================================ --}}
@php
    $isBuyingHere   = ($contract['direction'] ?? null) === 'buying';
    $isSellingHere  = ($contract['direction'] ?? null) === 'selling';
    $directionPill  = $isBuyingHere
        ? ['bg' => 'bg-accent/10',    'text' => 'text-accent',    'border' => 'border-accent/30',    'label' => __('contracts.direction_buying'),  'icon' => 'M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3', 'bar' => 'from-accent to-accent-h']
        : ['bg' => 'bg-accent-success/10', 'text' => 'text-accent-success', 'border' => 'border-accent-success/30', 'label' => __('contracts.direction_selling'), 'icon' => 'M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18', 'bar' => 'from-accent-success to-accent-success/80'];
    $cpRoleLabel    = $isBuyingHere ? __('contracts.supplier') : __('contracts.buyer');
    $cpName         = $contract['counterparty']['name'] ?? '—';
@endphp
<section class="relative overflow-hidden bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px] mb-6">
    {{-- Decorative accent bar --}}
    <div class="absolute top-0 bottom-0 start-0 w-[3px] bg-gradient-to-b {{ $directionPill['bar'] }}" aria-hidden="true"></div>
    {{-- Decorative gradient mesh --}}
    <div class="absolute inset-0 pointer-events-none opacity-60" style="background:
        radial-gradient(ellipse 60% 40% at 100% 0%, rgba(79,124,255,0.06) 0%, transparent 60%),
        radial-gradient(ellipse 50% 30% at 0% 100%, rgba(0,217,181,0.05) 0%, transparent 70%);"></div>

    <div class="relative">
        {{-- Top row: back link + PDF downloads --}}
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <a href="{{ route('dashboard.contracts') }}" class="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-primary transition-colors">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('contracts.title') }}
            </a>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'ar']) }}"
                   class="inline-flex items-center gap-1.5 px-3 h-9 rounded-[10px] text-[12px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('contracts.download_ar') }}
                </a>
                <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'en']) }}"
                   class="inline-flex items-center gap-1.5 px-3 h-9 rounded-[10px] text-[12px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('contracts.download_en') }}
                </a>
            </div>
        </div>

        {{-- Identity chips: id · status · direction --}}
        <div class="flex items-center gap-2 flex-wrap mb-3">
            <span class="text-[11px] font-mono text-muted px-2 h-[22px] inline-flex items-center rounded-md bg-page border border-th-border">{{ $contract['id'] }}</span>
            <x-dashboard.status-badge :status="$contract['status']" />
            <span class="inline-flex items-center gap-1 px-2.5 h-[22px] rounded-full border text-[10px] font-bold uppercase tracking-wider {{ $directionPill['bg'] }} {{ $directionPill['text'] }} {{ $directionPill['border'] }}">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $directionPill['icon'] }}"/></svg>
                {{ $directionPill['label'] }}
            </span>
        </div>

        {{-- Title --}}
        <h1 class="text-[26px] sm:text-[34px] font-bold text-primary leading-[1.15] tracking-[-0.02em] mb-1">{{ $contract['title'] }}</h1>
        <p class="text-[13px] text-muted mb-6">{{ __('contracts.details') }}</p>

        {{-- KPI strip — Amount · Progress · Days Left · Counterparty --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Amount --}}
            <div class="bg-page border border-th-border rounded-[12px] p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-accent-success/10 text-accent-success flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ __('contracts.total_value') }}</p>
                </div>
                <p class="text-[22px] sm:text-[24px] font-bold text-accent-success leading-none">{{ $contract['amount'] }}</p>
            </div>

            {{-- Progress --}}
            <div class="bg-page border border-th-border rounded-[12px] p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-accent/10 text-accent flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ __('common.progress') }}</p>
                </div>
                <p class="text-[22px] sm:text-[24px] font-bold text-primary leading-none mb-2">{{ $contract['progress'] }}%</p>
                <div class="w-full h-1 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-accent to-accent-success rounded-full transition-all duration-500"
                         style="width: {{ $contract['progress'] }}%"
                         role="progressbar"
                         aria-valuenow="{{ $contract['progress'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-label="{{ __('common.progress') }}"></div>
                </div>
            </div>

            {{-- Days remaining --}}
            <div class="bg-page border border-th-border rounded-[12px] p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-accent-warning/10 text-accent-warning flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ __('contracts.days_remaining') }}</p>
                </div>
                <p class="text-[22px] sm:text-[24px] font-bold text-primary leading-none">
                    {{ $contract['days_remaining'] ?? '—' }}
                    @if($contract['days_remaining'] !== null)<span class="text-[12px] font-medium text-muted ms-1">{{ __('common.days') }}</span>@endif
                </p>
            </div>

            {{-- Counterparty --}}
            <div class="bg-page border border-th-border rounded-[12px] p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-accent-violet/10 text-accent-violet flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ $cpRoleLabel }}</p>
                </div>
                <p class="text-[14px] sm:text-[15px] font-bold text-primary leading-tight truncate" title="{{ $cpName }}">{{ $cpName }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Internal-approval banner --}}
@if(!empty($contract['awaiting_internal_approval']))
<div class="mb-6 bg-gradient-to-r from-accent-violet/10 to-accent-info/10 border border-accent-violet/30 rounded-[16px] p-[17px] sm:p-[25px]">
    <div class="flex items-start gap-3 mb-3">
        <div class="w-11 h-11 rounded-[12px] bg-accent-violet/15 text-accent-violet flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[15px] font-bold text-primary">{{ __('contracts.approval_banner_title') }}</p>
            <p class="text-[12px] text-muted mt-0.5">{{ __('contracts.approval_banner_subtitle', ['amount' => $contract['amount']]) }}</p>
        </div>
    </div>

    @if(!empty($contract['can_approve_internally']))
    <form method="POST" action="{{ route('dashboard.contracts.approval', ['id' => $contract['numeric_id']]) }}"
          x-data="{ decision: 'approved' }"
          class="mt-4 p-4 bg-page border border-th-border rounded-[12px] space-y-3">
        @csrf
        <div>
            <label for="approval-notes" class="block text-[11px] uppercase tracking-wider font-semibold text-muted mb-2">
                {{ __('contracts.approval_notes_label') }}
            </label>
            <textarea
                id="approval-notes"
                name="notes"
                rows="3"
                maxlength="1000"
                placeholder="{{ __('contracts.approval_notes_placeholder') }}"
                class="w-full bg-surface border border-th-border rounded-[10px] px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 resize-none transition-all"
            ></textarea>
        </div>
        <input type="hidden" name="decision" :value="decision">
        <div class="flex items-center gap-2 flex-wrap">
            <button type="submit" @click="decision = 'approved'"
                    class="inline-flex items-center gap-2 px-4 h-10 rounded-[10px] text-[12px] font-bold text-white bg-accent-success hover:bg-accent-success/90 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('contracts.approval_approve') }}
            </button>
            <button type="submit" @click="decision = 'rejected'"
                    onclick="return confirm('{{ __('contracts.approval_reject_confirm') }}');"
                    class="inline-flex items-center gap-2 px-4 h-10 rounded-[10px] text-[12px] font-bold text-accent-danger bg-accent-danger/10 border border-accent-danger/30 hover:bg-accent-danger/20 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                {{ __('contracts.approval_reject') }}
            </button>
        </div>
    </form>
    @else
    <p class="mt-3 text-[11px] text-muted italic">{{ __('contracts.approval_waiting_for_approver') }}</p>
    @endif
</div>
@endif

{{-- Pre-signature alert --}}
@if($contract['status'] === 'pending')
<div class="mb-6 bg-gradient-to-r from-accent/10 to-accent-success/10 border border-accent/30 rounded-[16px] p-[17px] sm:p-[25px] flex items-start justify-between gap-4 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-11 h-11 rounded-[12px] bg-accent/15 text-accent flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
        </div>
        <div class="min-w-0">
            <p class="text-[15px] font-bold text-primary">{{ __('contracts.created_from_bid') }}</p>
            <p class="text-[12px] text-muted mt-0.5">{{ __('contracts.amendment_window_hint') }}</p>
        </div>
    </div>
    @if($contract['can_sign'])
        @if($contract['needs_signature_assets'])
            <button type="button" @click="$dispatch('open-signature-modal')"
                    class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                {{ __('contracts.upload_signature_cta') }}
            </button>
        @else
            <button type="button" @click="$dispatch('open-sign-modal')"
                    class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent-success hover:bg-accent-success/90 shadow-[0_10px_30px_-12px_rgba(0,217,181,0.55)] transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
                {{ __('contracts.sign_contract') }}
            </button>
        @endif
    @endif
</div>
@endif

{{-- ============================================================
     MAIN GRID — main column (2/3) + sidebar (1/3)
     ============================================================ --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ====================== MAIN COLUMN ====================== --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Parties Involved --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-accent" aria-hidden="true"></div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.parties') }}</h3>
            </div>
            <div class="space-y-3">
                @forelse($contract['parties'] as $party)
                <div class="bg-page border border-th-border rounded-[12px] p-4 hover:border-accent/30 transition-colors">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-[12px] {{ $party['color'] }} text-white text-[14px] font-bold flex items-center justify-center flex-shrink-0 shadow-[0_6px_20px_-8px_rgba(79,124,255,0.45)]" aria-hidden="true">{{ $party['code'] }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                @if($party['profile_url'])
                                <a href="{{ $party['profile_url'] }}" class="text-[14px] font-bold text-primary truncate hover:text-accent transition-colors">{{ $party['name'] }}</a>
                                @else
                                <p class="text-[14px] font-bold text-primary truncate">{{ $party['name'] }}</p>
                                @endif
                                <span class="text-[10px] font-bold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ $party['type'] }}</span>
                                @if($party['jurisdiction'])
                                <span class="text-[10px] font-bold text-accent-violet bg-accent-violet/10 border border-accent-violet/20 rounded-full px-2 py-0.5" title="{{ __('contracts.legal_jurisdiction') }}">{{ $party['jurisdiction'] }}</span>
                                @endif
                                @if(!is_null($party['icv_score']))
                                <span class="text-[10px] font-bold text-accent-success bg-accent-success/10 border border-accent-success/20 rounded-full px-2 py-0.5" title="{{ __('contracts.icv_score_tooltip') }}">
                                    ICV {{ number_format((float) $party['icv_score'], 1) }}
                                </span>
                                @endif
                                @if($party['is_insured'] === true)
                                <span class="text-[10px] font-bold text-accent-success bg-accent-success/10 border border-accent-success/20 rounded-full px-2 py-0.5 inline-flex items-center gap-1" title="{{ __('contracts.insured_tooltip') }}">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                    {{ __('contracts.insured') }}
                                </span>
                                @endif
                            </div>
                            @if($party['contact'])
                                <p class="text-[12px] text-muted truncate">{{ $party['contact'] }}</p>
                            @endif
                            @if($party['signed'])
                                <p class="text-[11px] text-accent-success inline-flex items-center gap-1 mt-1.5 font-semibold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                    {{ __('contracts.signed_on', ['date' => $party['signed_on']]) }}
                                </p>
                            @else
                                <p class="text-[11px] text-accent-warning inline-flex items-center gap-1 mt-1.5 font-semibold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    {{ __('contracts.awaiting_signature') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Legal identity strip --}}
                    @if($party['trn'] || $party['registration'] || $party['address'])
                    <div class="mt-3 pt-3 border-t border-th-border grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                        @if($party['trn'])
                        <div>
                            <p class="text-faint uppercase tracking-wider text-[10px] font-semibold">{{ __('contracts.trn') }}</p>
                            <p class="font-mono font-semibold text-primary">{{ $party['trn'] }}</p>
                        </div>
                        @endif
                        @if($party['registration'])
                        <div>
                            <p class="text-faint uppercase tracking-wider text-[10px] font-semibold">{{ __('contracts.registration_no') }}</p>
                            <p class="font-mono font-semibold text-primary">{{ $party['registration'] }}</p>
                        </div>
                        @endif
                        @if($party['address'])
                        <div class="min-w-0">
                            <p class="text-faint uppercase tracking-wider text-[10px] font-semibold">{{ __('contracts.address') }}</p>
                            <p class="font-medium text-primary truncate" title="{{ $party['address'] }}">{{ $party['address'] }}</p>
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- E-signature audit trail (UAE Federal Decree-Law 46/2021 Article 18) --}}
                    @if($party['signed'] && $party['sig_audit'])
                    <details class="mt-3 pt-3 border-t border-th-border group/audit">
                        <summary class="cursor-pointer text-[11px] font-semibold text-muted hover:text-primary inline-flex items-center gap-1 transition-colors">
                            <svg class="w-3.5 h-3.5 transition-transform group-open/audit:rotate-90 rtl:rotate-180 rtl:group-open/audit:rotate-270" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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

        {{-- Payment Schedule (component) --}}
        @if(!empty($contract['payment_schedule']))
        <x-payment-schedule
            :rows="$contract['payment_schedule']"
            :total="$contract['amount']"
            title="{{ __('contracts.payment_schedule') ?? 'Payment Schedule' }}"
            subtitle="{{ __('contracts.payment_schedule_hint') ?? 'Milestone breakdown for this contract.' }}" />
        @endif

        {{-- Line Items --}}
        @if(!empty($contract['line_items']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-accent-success" aria-hidden="true"></div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.line_items') }}</h3>
            </div>
            <div class="overflow-x-auto -mx-[17px] sm:-mx-[25px] px-[17px] sm:px-[25px]">
                <table class="w-full text-[13px]" role="table">
                    <thead>
                        <tr class="border-b border-th-border">
                            <th scope="col" class="text-start text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('contracts.item_name') }}</th>
                            <th scope="col" class="text-end text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('contracts.qty') }}</th>
                            <th scope="col" class="text-end text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('contracts.unit_price') }}</th>
                            <th scope="col" class="text-end text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('contracts.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contract['line_items'] as $item)
                        <tr class="border-b border-th-border last:border-b-0 hover:bg-page/50 transition-colors">
                            <td class="py-3">
                                <p class="text-[14px] font-medium text-primary">{{ $item['name'] }}</p>
                                @if($item['sku'])
                                <p class="text-[11px] text-muted mt-0.5 font-mono">{{ $item['sku'] }}</p>
                                @endif
                            </td>
                            <td class="py-3 text-end text-body tabular-nums">{{ $item['qty'] }}{{ $item['unit'] ? ' ' . $item['unit'] : '' }}</td>
                            <td class="py-3 text-end text-body tabular-nums">{{ $item['unit_price'] }}</td>
                            <td class="py-3 text-end text-accent-success font-bold tabular-nums">{{ $item['total'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="pt-4 text-end text-[11px] uppercase tracking-wider text-faint font-semibold">{{ __('contracts.total_value') }}</td>
                            <td class="pt-4 text-end text-[20px] font-bold text-accent-success tabular-nums">{{ $contract['amount'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- Payment Milestones --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-accent-warning" aria-hidden="true"></div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.payment_milestones') }}</h3>
            </div>
            <div class="space-y-3">
                @forelse($contract['milestones'] as $milestone)
                    @php
                        $status = $milestone['status'];
                        $wrapClasses = match($status) {
                            'paid'    => 'bg-accent-success/[0.06] border-accent-success/30',
                            'pending' => 'bg-accent-warning/[0.06] border-accent-warning/30',
                            default   => 'bg-page border-th-border',
                        };
                        $iconBg = match($status) {
                            'paid'    => 'bg-accent-success/15 text-accent-success',
                            'pending' => 'bg-accent-warning/15 text-accent-warning',
                            default   => 'bg-elevated text-muted',
                        };
                        $badgeClasses = match($status) {
                            'paid'    => 'text-accent-success bg-accent-success/10 border border-accent-success/20',
                            'pending' => 'text-accent-warning bg-accent-warning/10 border border-accent-warning/20',
                            default   => 'text-muted bg-elevated border border-th-border',
                        };
                    @endphp
                    <div class="{{ $wrapClasses }} border rounded-[12px] p-4 sm:p-5 flex items-center gap-4">
                        <div class="w-11 h-11 rounded-[10px] {{ $iconBg }} flex items-center justify-center flex-shrink-0">
                            @if($status === 'paid')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($status === 'pending')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <p class="text-[14px] font-bold text-primary">{{ $milestone['name'] }}</p>
                                <span class="text-[10px] font-bold {{ $badgeClasses }} rounded-full px-2 py-0.5 tabular-nums">{{ $milestone['percentage'] }}%</span>
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
                            <p class="text-[18px] font-bold text-accent tabular-nums">{{ $milestone['amount'] }}</p>
                            @if($status === 'pending' && $milestone['payment_id'])
                                @can('payment.process')
                                <form method="POST" action="{{ route('dashboard.payments.process', ['id' => $milestone['payment_id']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="mt-1.5 inline-flex items-center gap-1 px-3 h-7 rounded-[8px] text-[11px] font-bold text-white bg-accent hover:bg-accent-h transition-colors">{{ __('contracts.process_payment') }}</button>
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
            <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px] mb-5">
                <div class="flex items-start gap-2 mb-4">
                    <div class="w-1 h-5 rounded-full bg-accent-violet mt-1" aria-hidden="true"></div>
                    <div class="flex-1">
                        <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.amendments') }}</h3>
                        <p class="text-[12px] text-muted mt-0.5">{{ __('contracts.amendments_subtitle') }}</p>
                    </div>
                </div>

                @if($contract['can_amend'])
                <div class="mb-4 p-3 rounded-[10px] bg-accent/10 border border-accent/30 text-[12px] text-accent flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>
                    <span>{{ __('contracts.amendment_window_hint') }}</span>
                </div>
                @else
                <div class="mb-4 p-3 rounded-[10px] bg-accent-danger/10 border border-accent-danger/30 text-[12px] text-accent-danger flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    <span>{{ __('contracts.amendment_window_closed') }}</span>
                </div>
                @endif

                @if(empty($contract['amendments']))
                    <p class="text-[12px] text-muted">{{ __('contracts.amendment_no_pending') }}</p>
                @else
                <div class="space-y-3">
                    @foreach($contract['amendments'] as $a)
                        @php
                            $statusClasses = match($a['status']) {
                                'approved'         => 'text-accent-success bg-accent-success/10 border-accent-success/20',
                                'rejected'         => 'text-accent-danger bg-accent-danger/10 border-accent-danger/20',
                                'pending_approval' => 'text-accent-warning bg-accent-warning/10 border-accent-warning/20',
                                default            => 'text-muted bg-elevated border-th-border',
                            };
                            $statusLabel = __('contracts.amendment_status_' . $a['status']);
                            $kindLabel   = $a['kind'] === 'add'
                                ? __('contracts.amendment_kind_add')
                                : __('contracts.amendment_kind_modify');
                        @endphp
                        <div id="amendment-card-{{ $a['id'] }}" class="bg-page border border-th-border rounded-[12px] p-4">
                            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-bold text-primary bg-elevated border border-th-border rounded-full px-2 py-0.5">{{ $kindLabel }}</span>
                                    <span class="text-[10px] font-bold rounded-full px-2 py-0.5 border {{ $statusClasses }}">{{ $statusLabel }}</span>
                                    <span class="text-[11px] text-muted">{{ __('contracts.amendment_proposed_by', ['name' => $a['proposed_by']]) }} · {{ $a['proposed_at'] }}</span>
                                </div>
                            </div>
                            <p class="text-[11px] text-muted mb-2">{{ __('contracts.amendment_in_section', ['section' => $a['section_title']]) }}</p>

                            @if($a['kind'] === 'modify' && $a['old_text'])
                            <div class="mb-2">
                                <p class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-1">{{ __('contracts.amendment_old_text') }}</p>
                                <p class="text-[12px] text-body line-through opacity-70">{{ $a['old_text'] }}</p>
                            </div>
                            @endif
                            <div class="mb-2">
                                <p class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-1">{{ __('contracts.amendment_new_text') }}</p>
                                <p class="text-[12px] text-primary font-medium" dir="auto">{{ $a['new_text'] }}</p>
                            </div>
                            @if($a['reason'])
                            <p class="text-[11px] text-muted italic mt-2">"{{ $a['reason'] }}"</p>
                            @endif

                            {{-- Bilingual mismatch warning --}}
                            @if(!empty($a['bilingual_mismatch']) && $a['can_decide'])
                            <div class="mt-3 p-3 rounded-[10px] bg-accent-warning/10 border border-accent-warning/30 flex items-start gap-2">
                                <svg class="w-4 h-4 text-accent-warning flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                <p class="text-[11px] text-accent-warning leading-snug">
                                    {{ __('contracts.amendment_bilingual_warning', ['lang' => strtoupper($a['proposed_lang'] ?? '')]) }}
                                </p>
                            </div>
                            @endif

                            @if($a['can_decide'])
                            <div class="mt-3 flex items-center gap-2 flex-wrap">
                                <form method="POST" action="{{ route('dashboard.contracts.amendments.approve', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 h-9 rounded-[10px] text-[12px] font-bold text-white bg-accent-success hover:bg-accent-success/90 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                        {{ __('contracts.amendment_approve') }}
                                    </button>
                                </form>
                                <button type="button" @click="open({ kind: 'reject', amendmentId: {{ $a['id'] }} })" class="inline-flex items-center gap-1.5 px-3 h-9 rounded-[10px] text-[12px] font-bold text-accent-danger bg-accent-danger/10 border border-accent-danger/20 hover:bg-accent-danger/20 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ __('contracts.amendment_reject') }}
                                </button>
                            </div>

                            <form x-show="form && form.kind === 'reject' && form.amendmentId === {{ $a['id'] }}" x-cloak
                                  method="POST"
                                  action="{{ route('dashboard.contracts.amendments.reject', ['id' => $contract['numeric_id'], 'amendmentId' => $a['id']]) }}"
                                  onsubmit="return confirm('{{ __('contracts.amendment_confirm_reject') }}');"
                                  class="mt-3 p-3 bg-surface border border-th-border rounded-[10px] space-y-2">
                                @csrf
                                <label class="block text-[11px] text-muted">{{ __('contracts.amendment_rejection_reason') }}</label>
                                <textarea name="rejection_reason" rows="2" maxlength="500" class="w-full bg-page border border-th-border rounded-[8px] px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all"></textarea>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="px-3 h-9 rounded-[10px] text-[12px] font-bold text-white bg-accent-danger hover:bg-accent-danger/90 transition-colors">{{ __('contracts.amendment_reject') }}</button>
                                    <button type="button" @click="close()" class="px-3 h-9 rounded-[10px] text-[12px] font-medium text-muted hover:text-primary transition-colors">{{ __('contracts.amendment_cancel') }}</button>
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
            <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
                <div class="flex items-center gap-2 mb-5">
                    <div class="w-1 h-5 rounded-full bg-accent-info" aria-hidden="true"></div>
                    <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.terms_conditions') }}</h3>
                </div>
                <div class="space-y-5">
                    @forelse($contract['terms_sections'] as $i => $section)
                    <div class="group/section">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h4 class="text-[14px] font-bold text-primary inline-flex items-center gap-2">
                                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-md bg-accent/10 text-accent text-[11px] font-bold tabular-nums">{{ $i + 1 }}</span>
                                {{ $section['title'] }}
                            </h4>
                            @if($contract['can_amend'])
                            <button type="button"
                                    @click="open({ kind: 'add', sectionIndex: {{ $i }}, sectionTitle: @js($section['title']) })"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-accent hover:text-accent-h transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16m8-8H4"/></svg>
                                {{ __('contracts.amendment_add_clause') }}
                            </button>
                            @endif
                        </div>
                        <ul class="space-y-1.5 text-[13px] text-body ms-7">
                            @foreach($section['items'] as $j => $item)
                            <li class="group flex items-start gap-2 leading-relaxed">
                                <span class="text-accent" aria-hidden="true">•</span>
                                <span class="flex-1">{{ $item }}</span>
                                @if($contract['can_amend'])
                                <button type="button"
                                        @click="open({ kind: 'modify', sectionIndex: {{ $i }}, itemIndex: {{ $j }}, oldText: @js($item), sectionTitle: @js($section['title']) })"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1 text-[10px] font-medium text-accent hover:text-accent-h flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
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
                      class="mt-5 p-4 bg-page border border-th-border rounded-[12px] space-y-3">
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
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1">{{ __('contracts.amendment_old_text') }}</label>
                            <p class="text-[12px] text-muted italic line-through" x-text="form?.oldText"></p>
                        </div>
                    </template>

                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1">{{ __('contracts.amendment_new_text') }}</label>
                        <textarea name="new_text" rows="3" maxlength="2000" required
                                  class="w-full bg-surface border border-th-border rounded-[10px] px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1">{{ __('contracts.amendment_reason') }}</label>
                        <input type="text" name="reason" maxlength="500"
                               placeholder="{{ __('contracts.amendment_reason_placeholder') }}"
                               class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 h-10 rounded-[10px] text-[12px] font-bold text-white bg-accent hover:bg-accent-h transition-colors">{{ __('contracts.amendment_submit') }}</button>
                        <button type="button" @click="close()" class="px-4 h-10 rounded-[10px] text-[12px] font-medium text-muted hover:text-primary transition-colors">{{ __('contracts.amendment_cancel') }}</button>
                    </div>
                </form>
                @endif

                <div class="grid grid-cols-2 gap-2 mt-6">
                    <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'ar']) }}" class="inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ __('contracts.download_ar') }}
                    </a>
                    <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id'], 'lang' => 'en']) }}" class="inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ __('contracts.download_en') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================== SIDEBAR ====================== --}}
    <aside class="space-y-5">

        {{-- Phase 3 — Escrow panel --}}
        @if(!empty($contract['escrow']))
        @include('dashboard.contracts._escrow-panel', ['escrow' => $contract['escrow'], 'contract_id' => $contract['numeric_id']])
        @endif

        {{-- Quick Actions — primary CTAs first --}}
        <div x-data="{ panel: null }" class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-5 rounded-full bg-accent" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.quick_actions') }}</h3>
            </div>
            <div class="space-y-2.5">
                @if($contract['can_sign'])
                    @if($contract['needs_signature_assets'])
                    <button type="button" @click="$dispatch('open-signature-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        {{ __('contracts.upload_signature_cta') }}
                    </button>
                    @else
                    <button type="button" @click="$dispatch('open-sign-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent-success hover:bg-accent-success/90 shadow-[0_10px_30px_-12px_rgba(0,217,181,0.55)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                        </svg>
                        {{ __('contracts.sign_contract') }}
                    </button>
                    @endif
                @endif

                @if(!empty($contract['can_decline']))
                    <button type="button" @click="$dispatch('open-decline-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-accent-danger bg-accent-danger/5 border border-accent-danger/30 hover:bg-accent-danger/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('contracts.decline_contract') }}
                    </button>
                @endif

                @if(!empty($contract['can_terminate']))
                    <button type="button" @click="$dispatch('open-terminate-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-accent-danger bg-accent-danger/5 border border-accent-danger/30 hover:bg-accent-danger/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        {{ __('contracts.terminate_contract') }}
                    </button>
                @endif

                @if(!empty($contract['can_renew']))
                    <button type="button" @click="$dispatch('open-renew-modal')"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent-violet hover:bg-accent-violet/90 shadow-[0_10px_30px_-12px_rgba(139,92,246,0.55)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        {{ __('contracts.renew_contract') }}
                    </button>
                @endif

                @if($contract['has_shipment'] && $contract['shipment_id'])
                <a href="{{ route('dashboard.shipments.show', ['id' => $contract['shipment_id']]) }}" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    {{ __('contracts.track_shipment') }}
                </a>
                @endif

                <a href="{{ route('dashboard.disputes') }}" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                    {{ __('contracts.report_issue') }}
                </a>

                @can('ai.use')
                <a href="{{ route('dashboard.ai.copilot', ['contract' => $contract['numeric_id']]) }}"
                   class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-white bg-gradient-to-r from-accent-violet to-accent-info hover:opacity-95 shadow-[0_10px_30px_-12px_rgba(139,92,246,0.55)] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                    {{ __('contracts.ai_copilot_ask') }}
                </a>
                @endcan

                @if(auth()->user()?->hasPermission('cart.use') && auth()->user()?->company_id === ($contract['buyer_company_id'] ?? null))
                <form method="POST" action="{{ route('dashboard.contracts.reorder', ['id' => $contract['numeric_id']]) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        {{ __('cart.buy_again') }}
                    </button>
                </form>
                @endif

                {{-- Supplier-side fulfilment actions: gated on `my_role` per contract --}}
                @if(($contract['my_role'] ?? null) === 'supplier')
                <div class="pt-2 mt-1 border-t border-th-border">
                    <p class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-2">{{ __('contracts.supplier') }}</p>

                    {{-- Update Progress --}}
                    <button type="button" @click="panel = panel === 'progress' ? null : 'progress'"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5v9"/></svg>
                        {{ __('contracts.update_progress') }}
                    </button>
                    <form x-show="panel === 'progress'" x-cloak method="POST"
                          action="{{ route('dashboard.contracts.progress', ['id' => $contract['numeric_id']]) }}"
                          class="space-y-3 p-4 bg-page border border-th-border rounded-[12px] mb-2">
                        @csrf
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1.5">{{ __('contracts.progress_percent_label') }}</label>
                            <input type="number" name="progress_percentage" min="0" max="100" required
                                   value="{{ $contract['progress'] }}"
                                   class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1.5">{{ __('contracts.progress_note_label') }}</label>
                            <textarea name="note" rows="2" placeholder="{{ __('contracts.progress_note_placeholder') }}"
                                      class="w-full bg-surface border border-th-border rounded-[10px] px-3 py-2 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 resize-none transition-all"></textarea>
                        </div>
                        <button type="submit" class="w-full h-10 rounded-[10px] text-[12px] font-bold text-white bg-accent-success hover:bg-accent-success/90 transition-colors">
                            {{ __('common.save') }}
                        </button>
                    </form>

                    {{-- Upload Documents --}}
                    <button type="button" @click="panel = panel === 'documents' ? null : 'documents'"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        {{ __('contracts.upload_documents') }}
                    </button>
                    <form x-show="panel === 'documents'" x-cloak method="POST" enctype="multipart/form-data"
                          action="{{ route('dashboard.contracts.documents.upload', ['id' => $contract['numeric_id']]) }}"
                          class="space-y-3 p-4 bg-page border border-th-border rounded-[12px] mb-2">
                        @csrf
                        <input type="file" name="documents[]" multiple required
                               class="block w-full text-[12px] text-muted file:bg-accent file:text-white file:border-0 file:rounded-[8px] file:px-3 file:py-2 file:me-3 file:cursor-pointer file:text-[12px] file:font-semibold">
                        <p class="text-[11px] text-muted">{{ __('contracts.upload_documents_hint') }}</p>
                        <button type="submit" class="w-full h-10 rounded-[10px] text-[12px] font-bold text-white bg-accent hover:bg-accent-h transition-colors">
                            {{ __('common.upload') }}
                        </button>
                    </form>

                    {{-- Schedule Shipment --}}
                    <button type="button" @click="panel = panel === 'shipment' ? null : 'shipment'"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 hover:text-accent transition-colors mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                        {{ __('contracts.schedule_shipment') }}
                    </button>
                    <form x-show="panel === 'shipment'" x-cloak method="POST"
                          action="{{ route('dashboard.contracts.shipments.schedule', ['id' => $contract['numeric_id']]) }}"
                          class="space-y-3 p-4 bg-page border border-th-border rounded-[12px]">
                        @csrf
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1.5">{{ __('contracts.shipment_tracking_label') }}</label>
                            <input type="text" name="tracking_number" placeholder="{{ __('contracts.shipment_tracking_placeholder') }}"
                                   class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1.5">{{ __('contracts.shipment_carrier_label') }}</label>
                            <input type="text" name="carrier" placeholder="DHL, Aramex, FedEx…"
                                   class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="origin" placeholder="{{ __('contracts.shipment_origin') }}"
                                   class="bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                            <input type="text" name="destination" placeholder="{{ __('contracts.shipment_destination') }}"
                                   class="bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-faint font-semibold mb-1.5">{{ __('contracts.shipment_eta_label') }}</label>
                            <input type="date" name="estimated_delivery"
                                   class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        </div>
                        <button type="submit" class="w-full h-10 rounded-[10px] text-[12px] font-bold text-white bg-accent hover:bg-accent-h transition-colors">
                            {{ __('contracts.schedule') }}
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- Counterparty card — full contact block --}}
        @if(!empty($contract['counterparty']))
        @php $cp = $contract['counterparty']; @endphp
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 rounded-full bg-accent-success" aria-hidden="true"></div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.counterparty') }}</h3>
                </div>
                <span class="text-[10px] font-bold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5 uppercase tracking-wider">
                    {{ ($cp['role'] ?? '') === 'buyer' ? __('contracts.buyer') : __('contracts.supplier') }}
                </span>
            </div>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-0.5">{{ __('contracts.company') }}</dt>
                    <dd class="font-semibold text-primary">{{ $cp['name'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-0.5">{{ __('common.email') }}</dt>
                    <dd class="font-semibold text-primary break-all">{{ $cp['email'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-faint font-semibold mb-0.5">{{ __('common.phone') }}</dt>
                    <dd class="font-semibold text-primary">{{ $cp['phone'] ?? '—' }}</dd>
                </div>
            </dl>
        </div>
        @endif

        {{-- Signature & stamp on file --}}
        @if($contract['signature_assets']['has_both'])
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 rounded-full bg-accent-violet" aria-hidden="true"></div>
                    <h3 class="text-[14px] font-bold text-primary">{{ __('contracts.signature_label') }}</h3>
                </div>
                <button type="button" @click="$dispatch('open-signature-modal')" class="text-[11px] font-semibold text-accent hover:text-accent-h transition-colors">{{ __('contracts.signature_replace') }}</button>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-page border border-th-border rounded-[10px] p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['signature_url'] }}" alt="signature" class="max-h-12 w-auto">
                </div>
                <div class="flex-1 bg-page border border-th-border rounded-[10px] p-2 flex items-center justify-center min-h-[64px]">
                    <img src="{{ $contract['signature_assets']['stamp_url'] }}" alt="stamp" class="max-h-12 w-auto">
                </div>
            </div>
        </div>
        @endif

        {{-- AI Risk Analysis --}}
        @if(!empty($contract['risk']))
        @php
            $risk = $contract['risk'];
            $bandColor = match ($risk['band']) {
                'high'   => ['text' => 'text-accent-danger', 'bg' => 'bg-accent-danger/10', 'border' => 'border-accent-danger/30', 'ring' => '#ef4444'],
                'medium' => ['text' => 'text-accent-warning', 'bg' => 'bg-accent-warning/10', 'border' => 'border-accent-warning/30', 'ring' => '#ffb020'],
                default  => ['text' => 'text-accent-success', 'bg' => 'bg-accent-success/10', 'border' => 'border-accent-success/30', 'ring' => '#00d9b5'],
            };
        @endphp
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 rounded-full bg-accent-danger" aria-hidden="true"></div>
                    <div>
                        <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.risk_analysis') }}</h3>
                        <p class="text-[11px] text-muted mt-0.5">{{ __('contracts.risk_powered_by_ai') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full {{ $bandColor['bg'] }} {{ $bandColor['border'] }} border">
                    <span class="text-[18px] font-bold {{ $bandColor['text'] }} leading-none tabular-nums">{{ $risk['score'] }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $bandColor['text'] }}">{{ __('contracts.risk_band_' . $risk['band']) }}</span>
                </div>
            </div>
            @if(empty($risk['top_findings']))
                <p class="text-[12px] text-muted">{{ __('contracts.risk_no_findings') }}</p>
            @else
                <ul class="space-y-2.5">
                    @foreach($risk['top_findings'] as $finding)
                        @php
                            $sevColor = match ($finding['severity']) {
                                'high'   => 'text-accent-danger bg-accent-danger/10 border-accent-danger/20',
                                'medium' => 'text-accent-warning bg-accent-warning/10 border-accent-warning/20',
                                default  => 'text-muted bg-elevated border-th-border',
                            };
                        @endphp
                        <li class="flex items-start gap-2.5">
                            <span class="mt-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full px-2 py-0.5 border flex-shrink-0 {{ $sevColor }}">
                                {{ __('contracts.risk_severity_' . $finding['severity']) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12px] font-bold text-primary">{{ $finding['title'] }}</p>
                                <p class="text-[11px] text-muted leading-snug mt-0.5">{{ \Illuminate\Support\Str::limit($finding['description'], 140) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($risk['total_findings'] > count($risk['top_findings']))
                    <a href="{{ route('dashboard.ai.contract-risk', ['contract' => $contract['numeric_id']]) }}" class="mt-4 inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:text-accent-h transition-colors">
                        {{ __('contracts.risk_view_all', ['count' => $risk['total_findings']]) }}
                        <svg class="w-3 h-3 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endif
            @endif
        </div>
        @endif

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-accent-info" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.timeline') }}</h3>
            </div>
            <div class="space-y-5">
                @forelse($contract['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)<div class="absolute start-[5px] top-3 w-0.5 h-full bg-th-border"></div>@endif
                    <div class="w-2.5 h-2.5 rounded-full {{ $event['done'] ? 'bg-accent-success ring-2 ring-accent-success/20' : 'bg-th-border' }} mt-1.5 flex-shrink-0 z-10"></div>
                    <div class="flex-1 min-w-0 pb-2">
                        <p class="text-[10px] text-faint uppercase tracking-wider font-semibold">{{ $event['date'] }}</p>
                        <p class="text-[13px] font-bold text-primary mt-0.5">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted leading-snug mt-0.5">{{ $event['desc'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Internal team notes --}}
        @include('dashboard.contracts._internal-notes', [
            'contract_id'    => $contract['numeric_id'],
            'internal_notes' => $contract['internal_notes'] ?? [],
        ])

        {{-- Documents --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 rounded-full bg-accent-warning" aria-hidden="true"></div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.documents') }}</h3>
                </div>
                @if(!empty($contract['has_revisions']))
                <a href="{{ route('dashboard.contracts.diff', ['id' => $contract['numeric_id']]) }}"
                   class="inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:text-accent-h transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    {{ __('contracts.view_changes') }}
                </a>
                @endif
            </div>
            <div class="space-y-2">
                @forelse($contract['documents'] as $file)
                <div class="bg-page border border-th-border rounded-[10px] p-3 flex items-center gap-3 hover:border-accent/30 transition-colors">
                    <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    <span class="text-[12px] font-medium text-body flex-1 truncate">{{ $file['name'] }}</span>
                    @if($file['url'])
                        <a href="{{ $file['url'] }}" class="w-7 h-7 rounded-[8px] text-muted hover:text-accent hover:bg-accent/10 flex items-center justify-center transition-colors" aria-label="{{ __('common.download') }}"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg></a>
                    @endif
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        @if(!empty($contract['supplier_documents']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-5 rounded-full bg-accent-success" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.supplier_documents') ?? 'Supplier Documents' }}</h3>
            </div>
            <div class="space-y-2">
                @foreach($contract['supplier_documents'] as $doc)
                <a href="{{ $doc['url'] }}" class="bg-page border border-th-border rounded-[10px] p-3 flex items-center gap-3 hover:border-accent/40 transition-colors">
                    <div class="w-9 h-9 rounded-[8px] bg-accent-success/10 text-accent-success flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-semibold text-primary truncate">{{ $doc['name'] }}</p>
                        <p class="text-[11px] text-muted">{{ $doc['type'] }} · {{ $doc['size'] }} · {{ $doc['uploaded_at'] }}</p>
                    </div>
                    <svg class="w-3.5 h-3.5 text-muted flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($contract['progress_log']))
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-5 rounded-full bg-accent-success" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.progress_updates') ?? 'Progress Updates' }}</h3>
            </div>
            <div class="space-y-3">
                @foreach($contract['progress_log'] as $entry)
                <div class="flex items-start gap-3 pb-3 border-b border-th-border last:border-b-0 last:pb-0">
                    <div class="w-9 h-9 rounded-full bg-accent-success/15 text-accent-success flex items-center justify-center flex-shrink-0 text-[11px] font-bold tabular-nums">{{ $entry['percent'] }}%</div>
                    <div class="flex-1 min-w-0">
                        @if($entry['note'])
                        <p class="text-[12px] text-primary leading-[18px]">{{ $entry['note'] }}</p>
                        @else
                        <p class="text-[12px] text-muted italic">Progress updated to {{ $entry['percent'] }}%</p>
                        @endif
                        <p class="text-[10px] text-faint mt-0.5">{{ $entry['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </aside>
</div>

{{-- Rate this contract (only after completion) --}}
@if($contract['can_review'])
<div class="mt-6">
    <x-contract-review :contract-id="$contract['numeric_id']" :existing="$contract['existing_review']" />
</div>
@endif

{{-- ============================================================
     MODALS — unchanged behaviour, just preserved here. The
     redesign of internal modal chrome lives inside their own
     partials so we don't duplicate it.
     ============================================================ --}}

{{-- Signature & stamp upload modal — auto-opens when the buyer is about
     to sign but hasn't uploaded their assets yet. --}}
@include('dashboard.contracts._signature-modal', [
    'contract_id'      => $contract['numeric_id'],
    'signature_assets' => $contract['signature_assets'],
    'open'             => $contract['needs_signature_assets'],
])

{{-- Sign-contract confirmation modal — Federal Decree-Law 46/2021 --}}
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
    'button_class'=> 'bg-accent-danger hover:bg-accent-danger/90',
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
    'button_class'=> 'bg-accent-danger hover:bg-accent-danger/90',
    'min_length'  => 10,
])
@endif

{{-- Renew modal — clones the contract into a fresh PENDING_SIGNATURES draft --}}
@if(!empty($contract['can_renew']))
<div
    x-data="{ open: false, days: 365 }"
    x-on:open-renew-modal.window="open = true"
    x-on:keydown.escape.window="if (open) open = false"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none" role="dialog" aria-modal="true">
        <div class="pointer-events-auto w-full max-w-md bg-surface border border-th-border rounded-[16px] shadow-2xl overflow-hidden" @click.stop>
            <div class="flex items-start justify-between gap-3 p-6 border-b border-th-border">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-[12px] bg-accent-violet/15 text-accent-violet flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    </div>
                    <div>
                        <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.renew_modal_title') }}</h3>
                        <p class="text-[12px] text-muted mt-1 leading-relaxed">{{ __('contracts.renew_modal_subtitle', ['number' => $contract['id']]) }}</p>
                    </div>
                </div>
                <button type="button" @click="open = false" class="w-8 h-8 rounded-[10px] text-muted hover:text-primary hover:bg-elevated flex items-center justify-center flex-shrink-0 transition-colors" aria-label="{{ __('common.close') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('dashboard.contracts.renew', ['id' => $contract['numeric_id']]) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label for="renew-days" class="block text-[12px] font-semibold text-primary mb-2">{{ __('contracts.renew_duration_label') }}</label>
                    <div class="flex items-center gap-2">
                        <input id="renew-days" type="number" name="extend_days" x-model.number="days" min="1" max="1825" required
                               class="flex-1 bg-page border border-th-border rounded-[12px] px-3 h-11 text-[13px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                        <span class="text-[12px] text-muted">{{ __('common.days') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" @click="days = 90"  class="text-[10px] font-semibold px-2.5 h-7 rounded-[8px] bg-page border border-th-border text-muted hover:text-primary hover:border-accent/30 transition-colors">90d</button>
                        <button type="button" @click="days = 180" class="text-[10px] font-semibold px-2.5 h-7 rounded-[8px] bg-page border border-th-border text-muted hover:text-primary hover:border-accent/30 transition-colors">6mo</button>
                        <button type="button" @click="days = 365" class="text-[10px] font-semibold px-2.5 h-7 rounded-[8px] bg-page border border-th-border text-muted hover:text-primary hover:border-accent/30 transition-colors">1y</button>
                        <button type="button" @click="days = 730" class="text-[10px] font-semibold px-2.5 h-7 rounded-[8px] bg-page border border-th-border text-muted hover:text-primary hover:border-accent/30 transition-colors">2y</button>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="px-4 h-11 rounded-[12px] text-[13px] font-semibold text-muted hover:text-primary transition-colors">{{ __('contracts.amendment_cancel') }}</button>
                    <button type="submit" :disabled="days < 1" class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-bold text-white bg-accent-violet hover:bg-accent-violet/90 shadow-[0_10px_30px_-12px_rgba(139,92,246,0.55)] transition-all disabled:opacity-50">
                        {{ __('contracts.renew_contract') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
