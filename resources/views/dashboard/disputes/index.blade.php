@extends('layouts.dashboard', ['active' => 'disputes'])
@section('title', __('disputes.title'))

@section('content')

<x-dashboard.page-header :title="__('disputes.title')" :subtitle="__('disputes.subtitle')">
    @can('dispute.open')
    <x-slot:actions>
        <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('disputes.open_new') }}
        </button>
    </x-slot:actions>
    @endcan
</x-dashboard.page-header>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['open']" :label="__('disputes.open')" color="orange" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>' />
    <x-dashboard.stat-card :value="$stats['in_mediation']" :label="__('disputes.in_mediation')" color="blue" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6V3m0 18v-3m6-6h3M3 12h3m12.364-6.364l-2.121 2.121"/>' />
    <x-dashboard.stat-card :value="$stats['resolved']" :label="__('disputes.resolved')" color="green" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/>' />
    <x-dashboard.stat-card :value="$stats['resolution_rate']" :label="__('disputes.resolution_rate')" color="red" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307"/>' />
</div>

{{-- Platform Resolution banner --}}
<div class="bg-accent/5 border border-accent/20 rounded-2xl p-6 mb-6 flex items-start gap-4">
    <div class="w-12 h-12 rounded-xl bg-accent/15 flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623"/></svg>
    </div>
    <div class="flex-1">
        <h3 class="text-[16px] font-bold text-primary mb-2">{{ __('disputes.platform_resolution') }}</h3>
        <p class="text-[13px] text-muted leading-relaxed mb-3">{{ __('disputes.platform_text') }}</p>
        <div class="flex items-center gap-5 flex-wrap text-[12px]">
            <span class="inline-flex items-center gap-1.5 text-[#10B981] font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.avg_resolution') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#10B981] font-semibold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.certified_mediators') }}
            </span>
            <span class="inline-flex items-center gap-1.5 text-[#10B981] font-semibold">
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
        'high'   => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20'],
        'medium' => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20'],
        'low'    => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20'],
    ];
    @endphp

    @foreach($disputes as $d)
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
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
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
                @if($d['resolved_at'])<span>· Resolved: {{ $d['resolved_at'] }}</span>@endif
            </div>
            <div class="flex items-center gap-3">
                <span class="text-[11px] text-muted inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227"/></svg>
                    {{ __('disputes.messages', ['count' => $d['messages']]) }}
                </span>
                @php $disputeNumericId = preg_replace('/DIS-2024-(\d+)/', '$1', $d['id']); @endphp
                @if($d['status'] === 'resolved')
                <a href="{{ route('dashboard.disputes.show', ['id' => $disputeNumericId]) }}" class="px-4 py-2 rounded-xl text-[12px] font-semibold text-[#10B981] bg-[#10B981]/10 border border-[#10B981]/20 hover:bg-[#10B981]/15">{{ __('disputes.view_resolution') }}</a>
                @else
                <a href="{{ route('dashboard.disputes.show', ['id' => $disputeNumericId]) }}" class="px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('disputes.continue_discussion') }}</a>
                @endif
            </div>
        </div>

        @if(!empty($d['resolution']))
        <div class="mt-4 bg-[#10B981]/5 border border-[#10B981]/20 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#10B981] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
            <div>
                <p class="text-[13px] font-bold text-[#10B981] mb-0.5">{{ __('disputes.resolution_summary') }}</p>
                <p class="text-[12px] text-body">{{ $d['resolution'] }}</p>
            </div>
        </div>
        @endif
    </div>
    @endforeach
</div>

{{-- Dispute Types --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach([
        ['icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z', 'color' => '#EF4444', 'title' => __('disputes.quality_issues'), 'desc' => __('disputes.quality_issues_desc')],
        ['icon' => 'M12 6v6h4.5', 'color' => '#F59E0B', 'title' => __('disputes.late_delivery'), 'desc' => __('disputes.late_delivery_desc')],
        ['icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5', 'color' => '#3B82F6', 'title' => __('disputes.contract_violation'), 'desc' => __('disputes.contract_violation_desc')],
        ['icon' => 'M9 12.75L11.25 15 15 9.75', 'color' => '#10B981', 'title' => __('disputes.payment_disputes'), 'desc' => __('disputes.payment_disputes_desc')],
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

@endsection
