@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.title')" :subtitle="trim((auth()->user()->first_name ?? '') . ' · System Administrator')" />

@include('dashboard.admin._tabs', ['active' => 'overview'])

{{-- Top stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <x-dashboard.stat-card :value="$stats['users']"      :label="__('admin.users')"        color="blue" />
    <x-dashboard.stat-card :value="$stats['companies']"  :label="__('admin.companies')"    color="purple" />
    <x-dashboard.stat-card :value="$stats['pending_companies']" label="Pending Approvals"  color="orange" />
    <x-dashboard.stat-card :value="$stats['open_disputes']"     :label="__('disputes.open')" color="red" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-4">Recent Users</h3>
        <div class="divide-y divide-th-border">
            @forelse($recentUsers as $u)
            <div class="py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-accent/10 text-accent font-bold flex items-center justify-center text-[12px]">
                    {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                    <p class="text-[11px] text-muted">{{ $u->email }} · {{ $u->company?->name ?? '—' }}</p>
                </div>
                <span class="text-[10px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span>
            </div>
            @empty
            <p class="text-[13px] text-muted">No users yet.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-4">Recent Companies</h3>
        <div class="divide-y divide-th-border">
            @forelse($recentCompanies as $c)
            <div class="py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500/10 text-purple-500 font-bold flex items-center justify-center text-[12px]">
                    {{ strtoupper(substr($c->name ?? 'C', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary">{{ $c->name }}</p>
                    <p class="text-[11px] text-muted">{{ $c->city }} · {{ $c->country }}</p>
                </div>
                <x-dashboard.status-badge :status="$c->status?->value ?? 'pending'" />
            </div>
            @empty
            <p class="text-[13px] text-muted">No companies yet.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Audit log --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[16px] font-bold text-primary mb-4">{{ __('admin.audit') }}</h3>
    <div class="divide-y divide-th-border">
        @forelse($recentAuditLogs as $log)
        <div class="py-3 flex items-center gap-3 text-[12px]">
            <span class="text-muted">{{ $log->created_at?->diffForHumans() }}</span>
            <span class="font-mono text-accent">{{ $log->action?->value ?? $log->action }}</span>
            <span class="text-body">{{ $log->resource_type }}#{{ $log->resource_id }}</span>
            <span class="ms-auto text-muted">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }}</span>
        </div>
        @empty
        <p class="text-[13px] text-muted py-3">No audit entries.</p>
        @endforelse
    </div>
</div>

@endsection
