@extends('layouts.dashboard', ['active' => 'admin-health'])
@section('title', __('admin.health.title'))

@section('content')

<x-admin.navbar active="health" />

@php
    $statusColors = ['healthy' => '#00d9b5', 'warning' => '#ffb020', 'critical' => '#ff4d7f', 'down' => '#ef4444'];
    $overallColor = $statusColors[$health['overall']] ?? '#525252';
@endphp

{{-- Overall status banner --}}
<div class="bg-surface border border-th-border rounded-2xl p-6 mb-6 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none opacity-[0.06]" style="background: radial-gradient(circle at 90% 10%, {{ $overallColor }} 0%, transparent 50%);"></div>
    <div class="relative flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background: {{ $overallColor }}1A; border: 1px solid {{ $overallColor }}33;">
            @if($health['overall'] === 'healthy')
                <svg class="w-7 h-7" style="color: {{ $overallColor }}" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            @else
                <svg class="w-7 h-7" style="color: {{ $overallColor }}" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/></svg>
            @endif
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ __('admin.health.system_status') }}</p>
            <p class="text-[22px] font-bold text-primary capitalize">{{ $health['overall'] }}</p>
            <p class="text-[12px] text-muted">PHP {{ $health['php_version'] }} &middot; Laravel {{ $health['laravel_version'] }}</p>
        </div>
    </div>
</div>

{{-- Service status grid --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php $dbColor = $health['db_status'] === 'healthy' ? '#00d9b5' : '#ff4d7f'; @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $dbColor }}; box-shadow: 0 0 0 3px {{ $dbColor }}33;"></span>
            <span class="text-[13px] font-semibold text-primary">Database</span>
        </div>
        <p class="text-[24px] font-bold text-primary">{{ $health['db_latency_ms'] }}ms</p>
        <p class="text-[11px] text-muted">{{ ucfirst($health['db_status']) }}</p>
    </div>

    @php $cacheColor = $health['cache_status'] === 'healthy' ? '#00d9b5' : '#ff4d7f'; @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $cacheColor }}; box-shadow: 0 0 0 3px {{ $cacheColor }}33;"></span>
            <span class="text-[13px] font-semibold text-primary">Cache</span>
        </div>
        <p class="text-[24px] font-bold text-primary">{{ ucfirst($health['cache_status']) }}</p>
        <p class="text-[11px] text-muted">Memory: {{ $health['memory_usage_mb'] }}MB / {{ $health['memory_limit'] }}</p>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $health['disk_used_pct'] > 90 ? '#ff4d7f' : ($health['disk_used_pct'] > 75 ? '#ffb020' : '#00d9b5') }};"></span>
            <span class="text-[13px] font-semibold text-primary">Disk</span>
        </div>
        <p class="text-[24px] font-bold text-primary">{{ $health['disk_used_pct'] }}%</p>
        <p class="text-[11px] text-muted">{{ $health['disk_free_gb'] }}GB free / {{ $health['disk_total_gb'] }}GB</p>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $health['uploads_count'] > 0 ? '#00d9b5' : '#525252' }};"></span>
            <span class="text-[13px] font-semibold text-primary">Storage</span>
        </div>
        <p class="text-[24px] font-bold text-primary">{{ number_format($health['uploads_size_mb']) }}MB</p>
        <p class="text-[11px] text-muted">{{ number_format($health['uploads_count']) }} files</p>
    </div>
</div>

{{-- Queue & Jobs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ number_format($health['pending_jobs']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.health.pending_jobs') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold {{ $health['failed_jobs_24h'] > 0 ? 'text-[#ff4d7f]' : 'text-primary' }}">{{ number_format($health['failed_jobs_24h']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.health.failed_24h') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ number_format($health['failed_jobs']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.health.failed_total') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ number_format($health['audit_entries_24h']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.health.audit_24h') }}</p>
    </div>
</div>

{{-- E-Invoice queue --}}
@if($health['einvoice_failed'] > 0)
<div class="bg-[#ff4d7f]/5 border border-[#ff4d7f]/20 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <svg class="w-6 h-6 text-[#ff4d7f] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/></svg>
    <div>
        <p class="text-[14px] font-semibold text-[#ff4d7f]">{{ $health['einvoice_failed'] }} e-invoices failed/rejected</p>
        <p class="text-[12px] text-muted">Review the <a href="{{ route('admin.e-invoice.index') }}" class="text-accent hover:underline">e-invoice queue</a></p>
    </div>
</div>
@endif

@endsection
