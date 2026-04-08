@extends('layouts.dashboard', ['active' => 'disputes'])
@section('title', __('disputes.details'))

@section('content')

@php
$priorityColors = [
    'high'   => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20'],
    'medium' => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20'],
    'low'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20'],
];
$pc = $priorityColors[$dispute['priority']] ?? $priorityColors['low'];
@endphp

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <a href="{{ route('dashboard.disputes') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back') }}
        </a>
        <p class="text-[12px] font-mono text-muted mb-2">{{ $dispute['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary leading-tight">{{ $dispute['title'] }}</h1>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <x-dashboard.status-badge :status="$dispute['status']" />
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $pc['bg'] }} {{ $pc['text'] }} border {{ $pc['border'] }}">
                {{ __('priority.' . $dispute['priority']) }}
            </span>
            <span class="text-[13px] text-muted">{{ __('disputes.opened') }}: {{ $dispute['opened'] }}</span>
        </div>
    </div>
    <div class="flex items-center gap-3">
        @if(!$dispute['escalated'] && $dispute['status'] !== 'resolved')
            @can('dispute.escalate')
            <form method="POST" action="{{ route('dashboard.disputes.escalate', ['id' => $dispute['numeric_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#ffb020] hover:bg-[#e09800]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                    {{ __('disputes.escalate') }}
                </button>
            </form>
            @endcan
        @endif
        @if($dispute['status'] !== 'resolved')
            @can('dispute.resolve')
            <button type="button" onclick="document.getElementById('resolve-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('disputes.resolve') }}
            </button>
            @endcan
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Description --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-3">{{ __('disputes.description') }}</h3>
            <p class="text-[14px] text-body leading-relaxed">{{ $dispute['desc'] }}</p>
        </div>

        {{-- Resolution (if any) --}}
        @if($dispute['resolution'])
        <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/20 rounded-2xl p-6">
            <div class="flex items-start gap-3 mb-2">
                <svg class="w-6 h-6 text-[#00d9b5] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-[16px] font-bold text-[#00d9b5]">{{ __('disputes.resolution_summary') }}</h3>
            </div>
            <p class="text-[13px] text-body leading-relaxed mb-2">{{ $dispute['resolution'] }}</p>
            @if($dispute['resolved_at'])
            <p class="text-[11px] text-muted">{{ __('disputes.resolved_on') }}: {{ $dispute['resolved_at'] }}</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('disputes.parties') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.opened_by') }}</dt>
                    <dd class="font-semibold text-primary">{{ $dispute['opened_by'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.against') }}</dt>
                    <dd class="font-semibold text-primary">{{ $dispute['against'] }}</dd>
                </div>
                <div class="pt-3 border-t border-th-border">
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.contract') }}</dt>
                    <dd class="font-mono font-semibold text-accent">{{ $dispute['contract'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('common.contract_value') }}</dt>
                    <dd class="font-semibold text-[#00d9b5]">{{ $dispute['amount'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.type') }}</dt>
                    <dd class="font-semibold text-primary">{{ $dispute['type'] }}</dd>
                </div>
                @if($dispute['mediator'])
                <div class="pt-3 border-t border-th-border">
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.mediator') }}</dt>
                    <dd class="font-semibold text-primary">{{ $dispute['mediator'] }}</dd>
                </div>
                @endif
                @if($dispute['sla_due'])
                <div>
                    <dt class="text-[11px] text-muted uppercase tracking-wider">{{ __('disputes.sla_due') }}</dt>
                    <dd class="font-semibold text-primary">{{ $dispute['sla_due'] }}</dd>
                </div>
                @endif
                @if($dispute['escalated'])
                <div class="pt-3 border-t border-th-border">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20 rounded-full px-2.5 py-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                        {{ __('disputes.escalated_to_government') }}
                    </span>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>

{{-- Resolve modal --}}
@can('dispute.resolve')
<div id="resolve-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
    <div class="bg-surface border border-th-border rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-[18px] font-bold text-primary mb-4">{{ __('disputes.resolve') }}</h3>
        <form method="POST" action="{{ route('dashboard.disputes.resolve', ['id' => $dispute['numeric_id']]) }}" class="space-y-4">
            @csrf
            <textarea name="resolution" rows="5" required placeholder="{{ __('disputes.resolution_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 resize-none"></textarea>
            <div class="flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('resolve-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[13px] font-medium text-primary bg-page border border-th-border hover:bg-surface-2">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5]">{{ __('disputes.resolve') }}</button>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection
