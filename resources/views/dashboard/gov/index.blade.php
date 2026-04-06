@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.title'))

@section('content')

<x-dashboard.page-header :title="__('gov.title')" :subtitle="__('gov.intelligence')" />

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-dashboard.stat-card :value="$stats['escalated']" :label="__('gov.escalated_disputes')" color="red" />
    <x-dashboard.stat-card :value="$stats['resolved']"  :label="__('disputes.resolved')"     color="green" />
    <x-dashboard.stat-card :value="$stats['companies']" :label="__('admin.companies')"       color="blue" />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['gmv'])" label="Active GMV" color="purple" />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['vat_collected'])" label="VAT Collected" color="orange" />
</div>

{{-- Escalated queue --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('gov.escalated_disputes') }}</h3>
    <div class="space-y-4">
        @forelse($escalated as $d)
        <div class="bg-page border border-th-border rounded-xl p-5">
            <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">DIS-{{ $d->id }}</span>
                    <x-dashboard.status-badge :status="$d->status?->value ?? 'open'" />
                    <span class="text-[10px] text-[#F59E0B] bg-[#F59E0B]/10 border border-[#F59E0B]/20 rounded-full px-2 py-0.5 font-semibold">ESCALATED</span>
                </div>
                <span class="text-[11px] text-muted">{{ $d->created_at?->diffForHumans() }}</span>
            </div>
            <h4 class="text-[15px] font-bold text-primary mb-1">{{ $d->title }}</h4>
            <p class="text-[12px] text-muted mb-3">{{ \Illuminate\Support\Str::limit($d->description, 200) }}</p>
            <div class="flex items-center gap-4 text-[11px] text-muted flex-wrap">
                <span>Company: <strong class="text-body">{{ $d->company?->name ?? '—' }}</strong></span>
                <span>vs: <strong class="text-body">{{ $d->againstCompany?->name ?? '—' }}</strong></span>
                <span>Contract: <strong class="text-body">{{ $d->contract?->contract_number ?? '—' }}</strong></span>
                <a href="{{ route('dashboard.disputes.show', ['id' => $d->id]) }}" class="ms-auto text-accent font-semibold">Review →</a>
            </div>
        </div>
        @empty
        <p class="text-[13px] text-muted text-center py-8">{{ __('gov.no_escalated') }}</p>
        @endforelse
    </div>
</div>

@endsection
