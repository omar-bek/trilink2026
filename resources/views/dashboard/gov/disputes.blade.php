@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.disputes_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.disputes_title')" :subtitle="__('gov.disputes_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total'])" :label="__('gov.total_disputes')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['escalated'])" :label="__('gov.escalated_disputes')" color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>' />
    <x-dashboard.stat-card :value="number_format($stats['resolved'])" :label="__('disputes.resolved')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="$stats['avg_days'] . 'd'" :label="__('gov.avg_resolution_time')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-wrap gap-3">
    <select name="status" class="flex-1 min-w-[140px] bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
        <option value="">{{ __('common.all_statuses') }}</option>
        @foreach(['open','under_review','escalated','resolved'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2 px-4 h-10 bg-page border border-th-border rounded-xl text-[13px] text-primary cursor-pointer">
        <input type="checkbox" name="escalated_only" value="1" @checked(request('escalated_only')) class="rounded border-th-border">
        {{ __('gov.escalated_only') }}
    </label>
    <button type="submit" class="px-5 h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
    <a href="{{ route('gov.export', ['type' => 'disputes']) }}" class="px-4 h-10 rounded-xl text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        CSV
    </a>
</form>

{{-- Disputes list --}}
<div class="space-y-4 mb-8">
    @forelse($disputes as $d)
    @php
        $statusColors = ['open'=>'#ffb020','under_review'=>'#4f7cff','escalated'=>'#ff4d7f','resolved'=>'#00d9b5'];
        $sc = $statusColors[$d->status?->value ?? 'open'] ?? '#525252';
    @endphp
    <div class="bg-surface border border-th-border rounded-[16px] p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-2">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[12px] font-mono text-muted">DIS-{{ $d->id }}</span>
                    <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold" style="background:{{ $sc }}1A;border:1px solid {{ $sc }}33;color:{{ $sc }};">{{ ucfirst(str_replace('_',' ',$d->status?->value ?? 'open')) }}</span>
                    @if($d->escalated_to_government)
                    <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f]">GOV</span>
                    @endif
                </div>
                <p class="text-[14px] font-semibold text-primary">{{ $d->title }}</p>
            </div>
            <a href="{{ route('dashboard.disputes.show', $d->id) }}" class="px-3 h-8 rounded-lg text-[11px] font-semibold text-accent border border-accent/30 hover:bg-accent/5 inline-flex items-center">{{ __('common.review') }}</a>
        </div>
        <div class="flex items-center gap-4 text-[11px] text-muted flex-wrap">
            <span>{{ $d->company?->name ?? '—' }} vs {{ $d->againstCompany?->name ?? '—' }}</span>
            <span>{{ $d->contract?->contract_number ?? '—' }}</span>
            <span>{{ $d->created_at?->diffForHumans() }}</span>
        </div>
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-[16px] p-12 text-center">
        <p class="text-[14px] text-muted">{{ __('gov.no_disputes') }}</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $disputes->links() }}</div>

{{-- Precedents --}}
@if($precedents->count() > 0)
<div class="bg-surface border border-th-border rounded-[16px] p-5 mt-8">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('gov.precedents') }}</h3>
    <div class="space-y-3">
        @foreach($precedents as $p)
        <div class="bg-page border border-th-border rounded-xl p-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[12px] font-mono text-muted">DIS-{{ $p->id }}</span>
                <span class="text-[11px] text-muted">{{ $p->resolved_at?->format('d M Y') }}</span>
            </div>
            <p class="text-[13px] font-semibold text-primary mb-1">{{ $p->title }}</p>
            <p class="text-[12px] text-muted">{{ $p->resolution }}</p>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection
