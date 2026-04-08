@extends('layouts.dashboard', ['active' => 'disputes'])
@section('title', __('disputes.title'))

@section('content')

<x-dashboard.page-header :title="__('disputes.title')" :subtitle="__('disputes.subtitle')">
    @can('dispute.open')
    <x-slot:actions>
        <button type="button" onclick="document.getElementById('open-dispute-modal').classList.remove('hidden')"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('disputes.open_new') }}
        </button>
    </x-slot:actions>
    @endcan
</x-dashboard.page-header>

{{-- Stats — clickable; the resolution_rate one is purely informational. --}}
@php
    $disputeStatusCards = [
        ['key' => 'open',         'label' => __('disputes.open'),         'color' => 'orange', 'value' => $stats['open']],
        ['key' => 'in_mediation', 'label' => __('disputes.in_mediation'), 'color' => 'blue',   'value' => $stats['in_mediation']],
        ['key' => 'resolved',     'label' => __('disputes.resolved'),     'color' => 'green',  'value' => $stats['resolved']],
    ];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @foreach($disputeStatusCards as $card)
        <x-dashboard.stat-card
            :value="$card['value']"
            :label="$card['label']"
            :color="$card['color']"
            :href="route('dashboard.disputes', array_filter(['status' => $card['key'], 'q' => $search ?: null]))"
            :active="$statusFilter === $card['key']" />
    @endforeach
    <x-dashboard.stat-card :value="$stats['resolution_rate']" :label="__('disputes.resolution_rate')" color="red" />
</div>

{{-- Filter bar --}}
<x-dashboard.filter-bar
    :action="route('dashboard.disputes')"
    :search="$search"
    :placeholder="__('disputes.search_placeholder')"
    :clearUrl="route('dashboard.disputes')"
    :hasFilters="$search !== '' || $statusFilter !== 'all'"
    :count="$resultCount"
    countLabel="disputes.found">
    <x-slot:filters>
        <select name="status"
                class="w-full lg:w-[200px] bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
            <option value="all"          @selected($statusFilter === 'all')>{{ __('disputes.all_statuses') }}</option>
            <option value="open"         @selected($statusFilter === 'open')>{{ __('disputes.open') }}</option>
            <option value="in_mediation" @selected($statusFilter === 'in_mediation')>{{ __('disputes.in_mediation') }}</option>
            <option value="resolved"     @selected($statusFilter === 'resolved')>{{ __('disputes.resolved') }}</option>
        </select>
    </x-slot:filters>
</x-dashboard.filter-bar>

{{-- Platform Resolution banner --}}
<div class="bg-accent/5 border border-accent/20 rounded-2xl p-6 mb-6 flex items-start gap-4">
    <div class="w-12 h-12 rounded-xl bg-accent/15 flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623"/></svg>
    </div>
    <div class="flex-1">
        <h3 class="text-[16px] font-bold text-primary mb-2">{{ __('disputes.platform_resolution') }}</h3>
        <p class="text-[13px] text-muted leading-relaxed mb-3">{{ __('disputes.platform_text') }}</p>
        <div class="flex items-center gap-5 flex-wrap text-[12px]">
            <span class="inline-flex items-center gap-1.5 text-[#00d9b5] font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.avg_resolution') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#00d9b5] font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.certified_mediators') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#00d9b5] font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.uae_compliant') }}
            </span>
        </div>
    </div>
</div>

{{-- Disputes list --}}
<div class="space-y-4 mb-8">
    @php
    $priorityColors = [
        'high'   => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20'],
        'medium' => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20'],
        'low'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20'],
    ];
    @endphp

    @forelse($disputes as $d)
    @php $pc = $priorityColors[$d['priority']]; @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 mb-3 flex-wrap">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-[12px] font-mono text-muted">{{ $d['id'] }}</span>
                <x-dashboard.status-badge :status="$d['status']" />
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $pc['bg'] }} {{ $pc['text'] }} border {{ $pc['border'] }}">
                    {{ __('priority.' . $d['priority']) }}
                </span>
            </div>
            <div class="text-end">
                <p class="text-[20px] font-bold text-accent">{{ $d['amount'] }}</p>
                <p class="text-[11px] text-muted">{{ __('common.contract_value') }}</p>
            </div>
        </div>

        <h3 class="text-[18px] font-bold text-accent mb-2">{{ $d['title'] }}</h3>
        <p class="text-[13px] text-muted mb-4 leading-relaxed">{{ $d['desc'] }}</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[12px] text-muted mb-4">
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                {{ __('disputes.contract') }}: <span class="font-semibold text-body">{{ $d['contract'] }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                {{ __('contracts.supplier') }}: <span class="font-semibold text-body">{{ $d['supplier'] }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                {{ __('disputes.type') }}: <span class="font-semibold text-body">{{ $d['type'] }}</span>
            </span>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap pt-4 border-t border-th-border">
            <div class="flex items-center gap-5 text-[11px] text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                    {{ __('disputes.opened') }}: {{ $d['opened'] }}
                </span>
                @if($d['mediator'])
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749"/></svg>
                    {{ __('disputes.mediator') }}: {{ $d['mediator'] }}
                </span>
                @endif
                @if($d['resolved_at'])<span class="inline-flex items-center gap-1.5">
                    <svg class="w-3 h-3 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('disputes.resolved') }}: {{ $d['resolved_at'] }}
                </span>@endif
            </div>
            <div class="flex items-center gap-3">
                {{-- Message count is only rendered when there is an actual
                     thread to surface (no `dispute_messages` table yet). --}}
                @if(!empty($d['messages']))
                <span class="text-[11px] text-muted inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                    {{ __('disputes.messages', ['count' => $d['messages']]) }}
                </span>
                @endif
                @if($d['status'] === 'resolved')
                <a href="{{ route('dashboard.disputes.show', ['id' => $d['numeric_id']]) }}" class="px-4 py-2 rounded-xl text-[12px] font-semibold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20 hover:bg-[#00d9b5]/15">{{ __('disputes.view_resolution') }}</a>
                @else
                <a href="{{ route('dashboard.disputes.show', ['id' => $d['numeric_id']]) }}" class="px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('disputes.continue_discussion') }}</a>
                @endif
            </div>
        </div>

        @if(!empty($d['resolution']))
        <div class="mt-4 bg-[#00d9b5]/5 border border-[#00d9b5]/20 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#00d9b5] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
            <div>
                <p class="text-[13px] font-bold text-[#00d9b5] mb-0.5">{{ __('disputes.resolution_summary') }}</p>
                <p class="text-[12px] text-body">{{ $d['resolution'] }}</p>
            </div>
        </div>
        @endif
    </div>
    @empty
    @if($search !== '' || $statusFilter !== 'all')
        <x-dashboard.empty-state
            :title="__('disputes.no_results_title')"
            :message="__('disputes.no_results_message')"
            :cta="__('common.clear_filters')"
            :ctaUrl="route('dashboard.disputes')" />
    @else
        <x-dashboard.empty-state
            :title="__('disputes.empty_title')"
            :message="__('disputes.empty_message')" />
    @endif
    @endforelse
</div>

{{-- Dispute Types --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach([
        ['icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z', 'color' => '#ff4d7f', 'title' => __('disputes.quality_issues'), 'desc' => __('disputes.quality_issues_desc')],
        ['icon' => 'M12 6v6h4.5', 'color' => '#ffb020', 'title' => __('disputes.late_delivery'), 'desc' => __('disputes.late_delivery_desc')],
        ['icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5', 'color' => '#4f7cff', 'title' => __('disputes.contract_violation'), 'desc' => __('disputes.contract_violation_desc')],
        ['icon' => 'M9 12.75L11.25 15 15 9.75', 'color' => '#00d9b5', 'title' => __('disputes.payment_disputes'), 'desc' => __('disputes.payment_disputes_desc')],
    ] as $type)
    <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 transition-all">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-4" style="background: {{ $type['color'] }}15; border: 1px solid {{ $type['color'] }}30;">
            <svg class="w-5 h-5" style="color: {{ $type['color'] }};" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $type['icon'] }}"/></svg>
        </div>
        <h4 class="text-[14px] font-bold text-primary mb-1">{{ $type['title'] }}</h4>
        <p class="text-[12px] text-muted leading-relaxed">{{ $type['desc'] }}</p>
    </div>
    @endforeach
</div>

@can('dispute.open')
{{-- "Open New Dispute" modal --}}
<div id="open-dispute-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-th-border flex items-center justify-between">
            <h3 class="text-[18px] font-bold text-primary">{{ __('disputes.open_new') }}</h3>
            <button type="button" onclick="document.getElementById('open-dispute-modal').classList.add('hidden')"
                    class="text-muted hover:text-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        @if(empty($disputableContracts))
            <div class="p-6 text-center">
                <p class="text-[13px] text-muted">{{ __('disputes.no_contracts_to_dispute') }}</p>
            </div>
        @else
        <form method="POST" action="{{ route('dashboard.disputes.store') }}" class="p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('disputes.contract') }}</label>
                <select name="contract_id" required onchange="
                    const opt = this.options[this.selectedIndex];
                    document.getElementById('against-id').value = opt.dataset.against || '';
                    document.getElementById('against-label').textContent = opt.dataset.againstName || '—';
                "
                        class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                    <option value="">{{ __('disputes.select_contract') }}</option>
                    @foreach($disputableContracts as $c)
                        <option value="{{ $c['id'] }}"
                                data-against="{{ $c['against_company_id'] }}"
                                data-against-name="{{ $c['against_name'] }}">
                            {{ $c['contract_number'] }} — {{ $c['title'] }}
                        </option>
                    @endforeach
                </select>
                @error('contract_id')<p class="text-[11px] text-[#ff4d7f] mt-1">{{ $message }}</p>@enderror
            </div>

            <input type="hidden" id="against-id" name="against_company_id" value="">
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('disputes.against') }}</label>
                <p id="against-label" class="bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-muted">—</p>
            </div>

            <div>
                <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('disputes.type') }}</label>
                <select name="type" required
                        class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40">
                    <option value="quality">{{ __('disputes.quality_issues') }}</option>
                    <option value="delivery">{{ __('disputes.late_delivery') }}</option>
                    <option value="payment">{{ __('disputes.payment_disputes') }}</option>
                    <option value="contract_breach">{{ __('disputes.contract_violation') }}</option>
                    <option value="other">{{ __('common.other') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('common.title') }}</label>
                <input type="text" name="title" required maxlength="255"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40"
                       placeholder="{{ __('disputes.title_placeholder') }}">
                @error('title')<p class="text-[11px] text-[#ff4d7f] mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('common.description') }}</label>
                <textarea name="description" required rows="5" maxlength="5000"
                          class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 resize-none"
                          placeholder="{{ __('disputes.description_placeholder') }}"></textarea>
                @error('description')<p class="text-[11px] text-[#ff4d7f] mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('open-dispute-modal').classList.add('hidden')"
                        class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-muted bg-page border border-th-border hover:text-primary">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                    {{ __('disputes.submit') }}
                </button>
            </div>
        </form>
        @endif
    </div>
</div>

@if(isset($errors) && $errors->any())
{{-- Auto-open the modal when validation failed so the user sees the errors. --}}
<script>document.getElementById('open-dispute-modal')?.classList.remove('hidden');</script>
@endif
@endcan

@endsection
