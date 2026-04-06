@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.audit'))

@section('content')

<x-dashboard.page-header :title="__('admin.audit')" :subtitle="__('admin.audit_subtitle')" />

@include('dashboard.admin._tabs', ['active' => 'audit'])

<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
    <select name="action" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.audit_action') }}</option>
        @foreach($actions as $a)
            <option value="{{ $a->value }}" @selected($action === $a->value)>{{ $a->value }}</option>
        @endforeach
    </select>
    <select name="resource" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.audit_resource') }}</option>
        @foreach($resources as $r)
            <option value="{{ $r }}" @selected($resource === $r)>{{ $r }}</option>
        @endforeach
    </select>
    <input type="number" name="user_id" value="{{ $userId }}" placeholder="{{ __('admin.audit_user_id') }}"
           class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary placeholder-faint" />
    <input type="date" name="from" value="{{ $from }}" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
    <input type="date" name="to" value="{{ $to }}" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
    <button class="bg-accent text-white rounded-lg px-4 py-2 text-[13px] font-semibold">{{ __('common.filter') }}</button>
</form>

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2 text-faint text-[11px] uppercase tracking-wider">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('admin.audit_when') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.audit_user') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.audit_action') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.audit_resource') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.audit_ip') }}</th>
                    <th class="text-end px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($logs as $log)
                <tr class="hover:bg-surface-2/50">
                    <td class="px-4 py-3 text-[11px] text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="px-4 py-3 text-body">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }}</td>
                    <td class="px-4 py-3"><span class="font-mono text-[11px] text-accent bg-accent/10 border border-accent/20 rounded px-2 py-0.5">{{ $log->action?->value ?? $log->action }}</span></td>
                    <td class="px-4 py-3 text-body">{{ $log->resource_type }}#{{ $log->resource_id }}</td>
                    <td class="px-4 py-3 text-[11px] text-muted font-mono">{{ $log->ip_address }}</td>
                    <td class="px-4 py-3 text-end">
                        <a href="{{ route('admin.audit.show', $log->id) }}" class="text-accent text-[12px] hover:underline">{{ __('common.view_details') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-8">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-th-border">{{ $logs->links() }}</div>
</div>

@endsection
