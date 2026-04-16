@extends('layouts.dashboard', ['active' => 'admin-disputes'])
@section('title', __('admin.disputes.title'))

@section('content')

<x-admin.navbar active="disputes" />

<div class="mb-6">
    <h2 class="text-[20px] font-bold text-primary">{{ __('admin.disputes.title') }}</h2>
    <p class="text-[13px] text-muted mt-1">{{ __('admin.disputes.subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">{{ session('status') }}</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ $stats['total'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.total') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['open'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.open') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['escalated'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.escalated') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ef4444]">{{ $stats['overdue'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.overdue') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#00d9b5]">{{ $stats['resolved'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.resolved') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ $stats['avg_resolution_days'] }}d</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.disputes.avg_resolution') }}</p>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-wrap gap-3">
    <select name="status" class="flex-1 min-w-[140px] bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
        <option value="">{{ __('common.all_statuses') }}</option>
        @foreach(['open', 'under_review', 'escalated', 'resolved'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2 px-4 h-10 bg-page border border-th-border rounded-xl text-[13px] text-primary cursor-pointer">
        <input type="checkbox" name="overdue" value="1" @checked(request('overdue')) class="rounded border-th-border">
        {{ __('admin.disputes.overdue_only') }}
    </label>
    <button type="submit" class="px-5 h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
</form>

{{-- Dispute list --}}
<div class="space-y-4">
    @forelse($disputes as $d)
    @php
        $statusColors = ['open' => '#ffb020', 'under_review' => '#4f7cff', 'escalated' => '#ff4d7f', 'resolved' => '#00d9b5'];
        $sc = $statusColors[$d->status?->value ?? 'open'] ?? '#525252';
        $isOverdue = $d->sla_due_date && $d->sla_due_date->isPast() && ($d->status?->value ?? 'open') !== 'resolved';
    @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-5 {{ $isOverdue ? 'border-[#ef4444]/40' : '' }}">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[12px] font-mono text-muted">DIS-{{ $d->id }}</span>
                    <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold" style="background: {{ $sc }}1A; border: 1px solid {{ $sc }}33; color: {{ $sc }};">{{ ucfirst(str_replace('_', ' ', $d->status?->value ?? 'open')) }}</span>
                    @if($isOverdue)
                        <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold bg-[#ef4444]/10 border border-[#ef4444]/30 text-[#ef4444]">SLA OVERDUE</span>
                    @endif
                    @if($d->escalated_to_government)
                        <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f]">GOV</span>
                    @endif
                </div>
                <p class="text-[14px] font-semibold text-primary">{{ $d->title }}</p>
                <p class="text-[11px] text-muted mt-0.5">{{ $d->company?->name ?? '—' }} vs {{ $d->againstCompany?->name ?? '—' }} &middot; {{ $d->contract?->contract_number ?? '—' }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if(($d->status?->value ?? 'open') !== 'resolved')
                <form method="POST" action="{{ route('admin.disputes.assign', $d->id) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="assigned_to" class="bg-page border border-th-border rounded-lg px-2 h-8 text-[11px] text-primary">
                        <option value="">{{ __('admin.disputes.assign_to') }}</option>
                        @foreach($admins as $a)
                            <option value="{{ $a->id }}" @selected($d->assigned_to === $a->id)>{{ $a->first_name }} {{ $a->last_name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 h-8 rounded-lg text-[11px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('admin.disputes.assign') }}</button>
                </form>
                @endif
                <a href="{{ route('dashboard.disputes.show', $d->id) }}" class="px-3 h-8 rounded-lg text-[11px] font-semibold text-accent border border-accent/30 hover:bg-accent/5 inline-flex items-center">{{ __('common.view') }}</a>
            </div>
        </div>
        <div class="flex items-center gap-4 text-[11px] text-muted flex-wrap">
            <span>Raised by: <strong class="text-body">{{ $d->raisedByUser?->first_name ?? '—' }}</strong></span>
            <span>Assigned to: <strong class="text-body">{{ $d->assignedTo?->first_name ?? 'Unassigned' }}</strong></span>
            @if($d->sla_due_date)
                <span>SLA: <strong class="{{ $isOverdue ? 'text-[#ef4444]' : 'text-body' }}">{{ $d->sla_due_date->format('d M Y') }}</strong></span>
            @endif
            <span>{{ $d->created_at?->diffForHumans() }}</span>
        </div>
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
        <p class="text-[14px] text-muted">{{ __('admin.disputes.empty') }}</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $disputes->links() }}</div>

@endsection
