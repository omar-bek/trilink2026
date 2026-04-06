@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.audit'))

@section('content')

<x-dashboard.page-header :title="__('admin.audit') . ' #' . $log->id" :back="route('admin.audit.index')" />

@include('dashboard.admin._tabs', ['active' => 'audit'])

<div class="bg-surface border border-th-border rounded-2xl p-6 max-w-4xl">
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
        <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.audit_when') }}</dt><dd class="text-primary mt-1">{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.audit_user') }}</dt><dd class="text-primary mt-1">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }} ({{ $log->user?->email ?? '—' }})</dd></div>
        <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.audit_action') }}</dt><dd class="text-primary mt-1 font-mono">{{ $log->action?->value ?? $log->action }}</dd></div>
        <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.audit_resource') }}</dt><dd class="text-primary mt-1">{{ $log->resource_type }}#{{ $log->resource_id }}</dd></div>
        <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.audit_ip') }}</dt><dd class="text-primary mt-1 font-mono">{{ $log->ip_address }}</dd></div>
        <div><dt class="text-faint text-[11px] uppercase">User Agent</dt><dd class="text-body mt-1 text-[11px] break-all">{{ $log->user_agent }}</dd></div>
    </dl>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <p class="text-[11px] uppercase text-faint mb-2">{{ __('admin.audit_before') }}</p>
            <pre class="bg-surface-2 border border-th-border rounded-lg p-3 text-[11px] text-body overflow-x-auto">{{ $log->before ? json_encode($log->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '—' }}</pre>
        </div>
        <div>
            <p class="text-[11px] uppercase text-faint mb-2">{{ __('admin.audit_after') }}</p>
            <pre class="bg-surface-2 border border-th-border rounded-lg p-3 text-[11px] text-body overflow-x-auto">{{ $log->after ? json_encode($log->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '—' }}</pre>
        </div>
    </div>

    <div class="mt-6 pt-4 border-t border-th-border">
        <p class="text-[11px] uppercase text-faint mb-1">{{ __('admin.audit_hash') }}</p>
        <p class="text-[11px] text-muted font-mono break-all">{{ $log->hash }}</p>
    </div>
</div>

@endsection
