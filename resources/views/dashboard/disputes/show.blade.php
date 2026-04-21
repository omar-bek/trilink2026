@extends('layouts.dashboard', ['active' => 'disputes'])
@section('title', $dispute['title'])

@section('content')

@php
$priorityColors = [
    'high'   => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20'],
    'medium' => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20'],
    'low'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20'],
];
$pc = $priorityColors[$dispute['priority']] ?? $priorityColors['low'];
$viewer = $dispute['viewer'];
$viewerCompanyId = auth()->user()->company_id ?? null;

// Colour tokens per party so each side of the conversation is visually
// attributable at a glance — claimant = blue, respondent = teal,
// mediator/oversight = purple, system = neutral.
$partyColor = function (?int $companyId) use ($dispute) {
    if ($companyId === null) return 'system';
    if ($companyId === $dispute['claimant_id']) return 'claimant';
    if ($companyId === $dispute['respondent_id']) return 'respondent';
    return 'mediator';
};
@endphp

{{-- ─────────────────────── Header ─────────────────────── --}}
<div class="mb-6">
    <a href="{{ route('dashboard.disputes') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('common.back') }}
    </a>

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <p class="text-[12px] font-mono text-muted">{{ $dispute['id'] }}</p>
                @if($viewer['is_claimant'])
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-[#4f7cff]/10 text-[#4f7cff] border border-[#4f7cff]/20">{{ __('disputes.role.claimant') }}</span>
                @elseif($viewer['is_respondent'])
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-[#00d9b5]/10 text-[#00d9b5] border border-[#00d9b5]/20">{{ __('disputes.role.respondent') }}</span>
                @elseif($viewer['is_oversight'])
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-[#8B5CF6]/10 text-[#8B5CF6] border border-[#8B5CF6]/20">{{ __('disputes.role.mediator') }}</span>
                @endif
            </div>
            <h1 class="text-[24px] sm:text-[30px] font-bold text-primary leading-tight">{{ $dispute['title'] }}</h1>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
                <x-dashboard.status-badge :status="$dispute['status']" />
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $pc['bg'] }} {{ $pc['text'] }} border {{ $pc['border'] }}">
                    {{ __('severity.' . $dispute['severity']) }}
                </span>
                @if($dispute['escalated'])
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[#ffb020]/10 text-[#ffb020] border border-[#ffb020]/20">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                        {{ __('disputes.escalated_to_government') }}
                    </span>
                @endif
                <span class="text-[12px] text-muted">{{ __('disputes.opened') }}: {{ $dispute['opened'] }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($viewer['can_acknowledge'])
                <form method="POST" action="{{ route('dashboard.disputes.acknowledge', ['id' => $dispute['numeric_id']]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#4f7cff] hover:bg-[#3d6ae8]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                        {{ __('disputes.acknowledge') }}
                    </button>
                </form>
            @endif
            @if($viewer['can_escalate'])
                <form method="POST" action="{{ route('dashboard.disputes.escalate', ['id' => $dispute['numeric_id']]) }}" onsubmit="return confirm('{{ __('disputes.escalate_confirm') }}');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/30 hover:bg-[#ffb020]/15">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                        {{ __('disputes.escalate') }}
                    </button>
                </form>
            @endif
            @if($viewer['can_withdraw'])
                <button type="button" onclick="document.getElementById('withdraw-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-muted bg-page border border-th-border hover:text-primary">
                    {{ __('disputes.withdraw') }}
                </button>
            @endif
            @if($viewer['can_decide'])
                <button type="button" onclick="document.getElementById('decide-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#8B5CF6] hover:bg-[#7c4df0]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    {{ __('disputes.issue_decision') }}
                </button>
            @endif
        </div>
    </div>
</div>

{{-- Flash --}}
@if(session('status'))
<div class="mb-4 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 px-4 py-3 text-[13px] text-[#00d9b5] font-medium">
    {{ session('status') }}
</div>
@endif

{{-- ─────────────────────── SLA Banner ─────────────────────── --}}
@if(! in_array($dispute['status'], ['resolved', 'withdrawn', 'expired']))
    @if($dispute['response_overdue'])
        <div class="mb-4 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/5 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
            <div>
                <p class="text-[13px] font-bold text-[#ff4d7f]">{{ __('disputes.response_overdue_title') }}</p>
                <p class="text-[12px] text-muted">{{ __('disputes.response_overdue_desc', ['date' => $dispute['response_due']]) }}</p>
            </div>
        </div>
    @elseif($dispute['resolution_overdue'])
        <div class="mb-4 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/5 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71"/></svg>
            <div>
                <p class="text-[13px] font-bold text-[#ff4d7f]">{{ __('disputes.resolution_overdue_title') }}</p>
                <p class="text-[12px] text-muted">{{ __('disputes.resolution_overdue_desc', ['date' => $dispute['sla_due']]) }}</p>
            </div>
        </div>
    @elseif($dispute['response_due'] && ! $dispute['acknowledged_at'])
        <div class="mb-4 rounded-xl border border-[#ffb020]/30 bg-[#ffb020]/5 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#ffb020] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
            <div>
                <p class="text-[13px] font-bold text-[#ffb020]">{{ __('disputes.awaiting_ack_title') }}</p>
                <p class="text-[12px] text-muted">{{ __('disputes.awaiting_ack_desc', ['date' => $dispute['response_due']]) }}</p>
            </div>
        </div>
    @endif
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ─────────────────────── Main column ─────────────────────── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Structured claim --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-[15px] font-bold text-primary">{{ __('disputes.claim_details') }}</h3>
                <span class="text-[11px] text-muted font-mono">{{ __('disputes.type') }}: {{ $dispute['type'] }}</span>
            </div>
            <p class="text-[14px] text-body leading-relaxed mb-5">{{ $dispute['desc'] }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-page border border-th-border rounded-xl p-3">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.claim_amount') }}</p>
                    <p class="text-[15px] font-bold text-primary">{{ $dispute['claim_amount'] ?? '—' }}</p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-3">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.remedy') }}</p>
                    <p class="text-[13px] font-semibold text-primary">
                        {{ $dispute['requested_remedy'] ? __('disputes.remedy.' . $dispute['requested_remedy']) : '—' }}
                    </p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-3">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.severity') }}</p>
                    <p class="text-[13px] font-semibold text-primary">{{ __('severity.' . $dispute['severity']) }}</p>
                </div>
            </div>
        </div>

        {{-- Offers panel --}}
        @if(!empty($dispute['offers']) || $viewer['can_offer'])
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-[15px] font-bold text-primary flex items-center gap-2">
                    <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ __('disputes.settlement_offers') }}
                </h3>
                @if($viewer['can_offer'])
                <button type="button" onclick="document.getElementById('offer-modal').classList.remove('hidden')"
                        class="text-[12px] font-semibold text-accent hover:text-accent-h">
                    + {{ __('disputes.submit_offer') }}
                </button>
                @endif
            </div>

            @if(empty($dispute['offers']))
                <div class="text-center py-6 text-[13px] text-muted">{{ __('disputes.no_offers_yet') }}</div>
            @else
                <div class="space-y-3">
                    @foreach($dispute['offers'] as $o)
                        @php
                            $statusColor = match($o['status']) {
                                'accepted' => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/20',
                                'rejected' => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/20',
                                'countered' => 'bg-[#ffb020]/10 text-[#ffb020] border-[#ffb020]/20',
                                'expired' => 'bg-page text-muted border-th-border',
                                default => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/20',
                            };
                            $canActOnThis = $o['status'] === 'pending'
                                && !$o['expired']
                                && $o['from_company_id'] !== $viewerCompanyId
                                && ($viewer['is_claimant'] || $viewer['is_respondent']);
                        @endphp
                        <div class="border border-th-border rounded-xl p-4 {{ $o['status'] === 'pending' ? 'bg-page/50' : '' }}">
                            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                <div>
                                    <p class="text-[12px] text-muted mb-1">
                                        {{ __('disputes.offer_by', ['company' => $o['from'] ?? '—', 'user' => $o['by'] ?? '—']) }}
                                        @if($o['parent_offer_id'])
                                            <span class="text-[10px] text-[#ffb020] ms-2">↩ {{ __('disputes.counter_offer') }}</span>
                                        @endif
                                    </p>
                                    <p class="text-[20px] font-bold text-primary">{{ $o['amount'] }}</p>
                                    @if($o['remedy'])
                                        <p class="text-[11px] text-muted mt-0.5">{{ __('disputes.remedy.' . $o['remedy']) }}</p>
                                    @endif
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusColor }}">
                                    {{ __('disputes.offer_status.' . $o['status']) }}
                                </span>
                            </div>
                            <p class="text-[13px] text-body leading-relaxed mb-3 whitespace-pre-line">{{ $o['terms'] }}</p>

                            <div class="flex items-center justify-between gap-3 flex-wrap text-[11px] text-muted">
                                <span>{{ $o['at'] }}</span>
                                @if($o['status'] === 'pending' && $o['expires'] && !$o['expired'])
                                    <span class="text-[#ffb020]">{{ __('disputes.expires_on', ['date' => $o['expires']]) }}</span>
                                @elseif($o['expired'])
                                    <span class="text-[#ff4d7f]">{{ __('disputes.offer_expired') }}</span>
                                @endif
                            </div>

                            @if($o['response_note'])
                                <div class="mt-3 pt-3 border-t border-th-border text-[12px] text-muted">
                                    <span class="font-semibold text-primary">{{ __('disputes.response_note') }}:</span>
                                    {{ $o['response_note'] }}
                                </div>
                            @endif

                            @if($canActOnThis)
                            <div class="mt-4 pt-3 border-t border-th-border flex items-center gap-2 flex-wrap">
                                <form method="POST" action="{{ route('dashboard.disputes.offers.respond', ['id' => $dispute['numeric_id'], 'offerId' => $o['id']]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" onclick="return confirm('{{ __('disputes.accept_offer_confirm', ['amount' => $o['amount']]) }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                        {{ __('disputes.accept_offer') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.disputes.offers.respond', ['id' => $dispute['numeric_id'], 'offerId' => $o['id']]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/15">
                                        {{ __('disputes.reject_offer') }}
                                    </button>
                                </form>
                                <button type="button" onclick="document.getElementById('counter-offer-{{ $o['id'] }}').classList.remove('hidden')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20 hover:bg-[#ffb020]/15">
                                    {{ __('disputes.counter_offer') }}
                                </button>
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Conversation thread --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                {{ __('disputes.conversation') }}
            </h3>

            @if(empty($dispute['messages']))
                <div class="text-center py-8 text-[13px] text-muted">{{ __('disputes.no_messages_yet') }}</div>
            @else
                <div class="space-y-3 mb-5 max-h-[500px] overflow-y-auto pr-2">
                    @foreach($dispute['messages'] as $m)
                        @php
                            $role = $partyColor($m['company_id']);
                            $isViewer = $m['company_id'] === $viewerCompanyId;
                        @endphp
                        @if($m['is_system'])
                            <div class="flex justify-center py-1">
                                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-page border border-th-border text-[11px] text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></svg>
                                    <span>{{ $m['body'] }}</span>
                                    <span class="text-faint">·</span>
                                    <span class="text-faint">{{ $m['at'] }}</span>
                                </div>
                            </div>
                        @else
                            <div class="flex gap-3 {{ $isViewer ? 'flex-row-reverse' : '' }}">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white flex-shrink-0
                                    @if($role === 'claimant') bg-[#4f7cff]
                                    @elseif($role === 'respondent') bg-[#00d9b5]
                                    @else bg-[#8B5CF6]
                                    @endif">
                                    {{ strtoupper(substr($m['author'] ?? 'U', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0 max-w-[85%] {{ $isViewer ? 'text-end' : '' }}">
                                    <div class="flex items-center gap-2 mb-1 {{ $isViewer ? 'justify-end' : '' }} flex-wrap">
                                        <span class="text-[12px] font-semibold text-primary">{{ $m['author'] ?? '—' }}</span>
                                        <span class="text-[11px] text-muted">·</span>
                                        <span class="text-[11px] text-muted">{{ $m['company'] ?? '—' }}</span>
                                        @if($m['is_internal'])
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-[#ffb020]/10 text-[#ffb020]">{{ __('disputes.internal') }}</span>
                                        @endif
                                    </div>
                                    <div class="inline-block text-start rounded-2xl px-4 py-2.5 text-[13px] text-body leading-relaxed whitespace-pre-line
                                        @if($m['is_internal']) bg-[#ffb020]/5 border border-[#ffb020]/20
                                        @elseif($role === 'claimant') bg-[#4f7cff]/5 border border-[#4f7cff]/15
                                        @elseif($role === 'respondent') bg-[#00d9b5]/5 border border-[#00d9b5]/15
                                        @else bg-page border border-th-border
                                        @endif">
                                        {{ $m['body'] }}
                                    </div>
                                    <p class="text-[10px] text-muted mt-1">{{ $m['at_full'] }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($viewer['can_message'])
            <form method="POST" action="{{ route('dashboard.disputes.message', ['id' => $dispute['numeric_id']]) }}" class="space-y-2">
                @csrf
                <textarea name="body" rows="3" required maxlength="5000" placeholder="{{ __('disputes.message_placeholder') }}"
                          class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary focus:outline-none focus:border-accent/50 resize-none"></textarea>
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    @if($viewer['is_party'])
                    <label class="flex items-center gap-2 text-[12px] text-muted cursor-pointer">
                        <input type="checkbox" name="internal" value="1" class="accent-[#ffb020]">
                        {{ __('disputes.internal_note_label') }}
                    </label>
                    @else
                    <span></span>
                    @endif
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        {{ __('disputes.send_message') }}
                    </button>
                </div>
            </form>
            @endif
        </div>

        {{-- Resolution card (if resolved) --}}
        @if($dispute['resolution'] || $dispute['decision_outcome'])
        <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/20 rounded-2xl p-6">
            <div class="flex items-start gap-3 mb-3">
                <svg class="w-6 h-6 text-[#00d9b5] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="flex-1">
                    <h3 class="text-[16px] font-bold text-[#00d9b5]">{{ __('disputes.resolution_summary') }}</h3>
                    @if($dispute['decision_outcome'])
                        <p class="text-[12px] text-muted mt-0.5">{{ __('disputes.outcome.' . $dispute['decision_outcome']) }}</p>
                    @endif
                </div>
                @if($dispute['decision_amount'])
                    <div class="text-end">
                        <p class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.awarded_amount') }}</p>
                        <p class="text-[18px] font-bold text-[#00d9b5]">{{ $dispute['decision_amount'] }}</p>
                    </div>
                @endif
            </div>
            @if($dispute['resolution'])
                <p class="text-[13px] text-body leading-relaxed mb-3 whitespace-pre-line">{{ $dispute['resolution'] }}</p>
            @endif
            <div class="flex items-center gap-4 text-[11px] text-muted flex-wrap pt-3 border-t border-[#00d9b5]/20">
                @if($dispute['resolved_at'])
                    <span>{{ __('disputes.resolved_on') }}: {{ $dispute['resolved_at'] }}</span>
                @endif
                @if($dispute['decided_by'])
                    <span>{{ __('disputes.decided_by') }}: {{ $dispute['decided_by'] }}</span>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- ─────────────────────── Sidebar ─────────────────────── --}}
    <div class="space-y-6">
        {{-- Parties --}}
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <h3 class="text-[13px] font-bold text-primary mb-3 uppercase tracking-wider">{{ __('disputes.parties') }}</h3>
            <div class="space-y-3">
                <div class="bg-page rounded-xl p-3 border-l-2 rtl:border-l-0 rtl:border-r-2 border-[#4f7cff]">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.role.claimant') }}</p>
                    <p class="text-[13px] font-bold text-primary">{{ $dispute['claimant'] }}</p>
                    <p class="text-[11px] text-muted mt-1">{{ $dispute['opened_by'] }}</p>
                </div>
                <div class="bg-page rounded-xl p-3 border-l-2 rtl:border-l-0 rtl:border-r-2 border-[#00d9b5]">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.role.respondent') }}</p>
                    <p class="text-[13px] font-bold text-primary">{{ $dispute['against'] }}</p>
                    @if($dispute['acknowledged_at'])
                        <p class="text-[11px] text-[#00d9b5] mt-1">✓ {{ __('disputes.ack_on', ['date' => $dispute['acknowledged_at']]) }}</p>
                    @else
                        <p class="text-[11px] text-[#ffb020] mt-1">{{ __('disputes.awaiting_ack') }}</p>
                    @endif
                </div>
                @if($dispute['mediator'])
                <div class="bg-page rounded-xl p-3 border-l-2 rtl:border-l-0 rtl:border-r-2 border-[#8B5CF6]">
                    <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('disputes.mediator') }}</p>
                    <p class="text-[13px] font-bold text-primary">{{ $dispute['mediator'] }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Contract --}}
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <h3 class="text-[13px] font-bold text-primary mb-3 uppercase tracking-wider">{{ __('disputes.contract') }}</h3>
            <p class="text-[14px] font-mono font-semibold text-accent mb-2">{{ $dispute['contract'] }}</p>
            <p class="text-[11px] text-muted uppercase tracking-wider">{{ __('common.contract_value') }}</p>
            <p class="text-[16px] font-bold text-[#00d9b5]">{{ $dispute['amount'] }}</p>
        </div>

        {{-- SLA --}}
        @if($dispute['response_due'] || $dispute['sla_due'])
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <h3 class="text-[13px] font-bold text-primary mb-3 uppercase tracking-wider">{{ __('disputes.sla') }}</h3>
            <dl class="space-y-3 text-[12px]">
                @if($dispute['response_due'])
                <div>
                    <dt class="text-[10px] text-muted uppercase tracking-wider">{{ __('disputes.response_due') }}</dt>
                    <dd class="font-semibold {{ $dispute['response_overdue'] ? 'text-[#ff4d7f]' : 'text-primary' }}">
                        {{ $dispute['response_due'] }}
                    </dd>
                </div>
                @endif
                @if($dispute['sla_due'])
                <div>
                    <dt class="text-[10px] text-muted uppercase tracking-wider">{{ __('disputes.resolution_due') }}</dt>
                    <dd class="font-semibold {{ $dispute['resolution_overdue'] ? 'text-[#ff4d7f]' : 'text-primary' }}">
                        {{ $dispute['sla_due'] }}
                    </dd>
                </div>
                @endif
            </dl>
        </div>
        @endif

        {{-- Timeline --}}
        @if(!empty($dispute['timeline']))
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <h3 class="text-[13px] font-bold text-primary mb-4 uppercase tracking-wider">{{ __('disputes.timeline') }}</h3>
            <ol class="relative border-s-2 border-th-border space-y-4 ms-1">
                @foreach($dispute['timeline'] as $e)
                <li class="ms-4">
                    <div class="absolute w-2 h-2 bg-accent rounded-full -start-[5px] mt-1.5"></div>
                    <p class="text-[12px] font-semibold text-primary">{{ __('disputes.event.' . $e['event']) }}</p>
                    <p class="text-[11px] text-muted">
                        @if($e['actor'])
                            {{ $e['actor'] }}
                            @if($e['company']) · {{ $e['company'] }}@endif
                        @endif
                    </p>
                    <p class="text-[10px] text-faint mt-0.5">{{ $e['at_full'] }}</p>
                </li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>
</div>

{{-- ─────────────────────── Modals ─────────────────────── --}}

{{-- Submit offer modal --}}
@if($viewer['can_offer'])
<div id="offer-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-5 border-b border-th-border flex items-center justify-between">
            <h3 class="text-[16px] font-bold text-primary">{{ __('disputes.submit_offer') }}</h3>
            <button type="button" onclick="document.getElementById('offer-modal').classList.add('hidden')" class="text-muted hover:text-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('dashboard.disputes.offers.submit', ['id' => $dispute['numeric_id']]) }}" class="p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.amount') }}</label>
                    <input type="number" name="amount" required min="0" step="0.01"
                           class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('common.currency') }}</label>
                    <input type="text" name="currency" value="AED" maxlength="3"
                           class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary uppercase focus:outline-none focus:border-accent/40">
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.remedy') }}</label>
                <select name="remedy" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                    <option value="">—</option>
                    @foreach($remedies as $r)
                        <option value="{{ $r->value }}">{{ __('disputes.remedy.' . $r->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.offer_terms') }}</label>
                <textarea name="terms" rows="4" required maxlength="2000" placeholder="{{ __('disputes.offer_terms_placeholder') }}"
                          class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40 resize-none"></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.expires_in_days') }}</label>
                <input type="number" name="expires_in_days" value="7" min="1" max="60"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('offer-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] font-medium text-primary bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('disputes.submit_offer') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Counter-offer modals (one per pending counterable offer) --}}
@foreach($dispute['offers'] as $o)
    @if($o['status'] === 'pending' && !$o['expired'] && $o['from_company_id'] !== $viewerCompanyId && ($viewer['is_claimant'] || $viewer['is_respondent']))
    <div id="counter-offer-{{ $o['id'] }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
         onclick="if(event.target === this) this.classList.add('hidden')">
        <div class="bg-surface border border-th-border rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-5 border-b border-th-border flex items-center justify-between">
                <h3 class="text-[16px] font-bold text-primary">{{ __('disputes.counter_offer') }}</h3>
                <button type="button" onclick="document.getElementById('counter-offer-{{ $o['id'] }}').classList.add('hidden')" class="text-muted hover:text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('dashboard.disputes.offers.respond', ['id' => $dispute['numeric_id'], 'offerId' => $o['id']]) }}" class="p-5 space-y-4">
                @csrf
                <input type="hidden" name="action" value="counter">
                <p class="text-[12px] text-muted">{{ __('disputes.countering_offer', ['amount' => $o['amount']]) }}</p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.your_amount') }}</label>
                        <input type="number" name="amount" required min="0" step="0.01"
                               class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('common.currency') }}</label>
                        <input type="text" name="currency" value="{{ $o['currency'] }}" maxlength="3"
                               class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary uppercase focus:outline-none focus:border-accent/40">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.offer_terms') }}</label>
                    <textarea name="terms" rows="4" required maxlength="2000"
                              class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40 resize-none"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" onclick="document.getElementById('counter-offer-{{ $o['id'] }}').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] font-medium text-primary bg-page border border-th-border">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-[#ffb020] hover:bg-[#e09800]">{{ __('disputes.send_counter') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endforeach

{{-- Withdraw modal --}}
@if($viewer['can_withdraw'])
<div id="withdraw-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-5 border-b border-th-border">
            <h3 class="text-[16px] font-bold text-primary">{{ __('disputes.withdraw') }}</h3>
            <p class="text-[12px] text-muted mt-1">{{ __('disputes.withdraw_warning') }}</p>
        </div>
        <form method="POST" action="{{ route('dashboard.disputes.withdraw', ['id' => $dispute['numeric_id']]) }}" class="p-5 space-y-4">
            @csrf
            <textarea name="reason" rows="4" maxlength="1000" placeholder="{{ __('disputes.withdraw_reason_placeholder') }}"
                      class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40 resize-none"></textarea>
            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('withdraw-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] font-medium text-primary bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#e83a6c]">{{ __('disputes.confirm_withdraw') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Decision modal (oversight only) --}}
@if($viewer['can_decide'])
<div id="decide-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-5 border-b border-th-border">
            <h3 class="text-[16px] font-bold text-primary">{{ __('disputes.issue_decision') }}</h3>
        </div>
        <form method="POST" action="{{ route('dashboard.disputes.resolve', ['id' => $dispute['numeric_id']]) }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.outcome_label') }}</label>
                <select name="decision_outcome" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                    @foreach($outcomes as $oc)
                        <option value="{{ $oc->value }}">{{ __('disputes.outcome.' . $oc->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.awarded_amount') }} ({{ __('common.optional') }})</label>
                <input type="number" name="decision_amount" min="0" step="0.01"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('disputes.reasoning') }}</label>
                <textarea name="resolution" rows="5" required maxlength="2000" placeholder="{{ __('disputes.reasoning_placeholder') }}"
                          class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent/40 resize-none"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="document.getElementById('decide-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] font-medium text-primary bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-[#8B5CF6] hover:bg-[#7c4df0]">{{ __('disputes.issue_decision') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
