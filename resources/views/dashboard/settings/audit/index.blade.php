@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.audit_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.audit_title')" :subtitle="__('settings.audit_subtitle')" />

<form method="GET" action="{{ route('settings.audit.index') }}" class="bg-surface border border-th-border rounded-2xl p-4 mb-5 grid grid-cols-1 md:grid-cols-4 gap-3">
    <input type="text" name="action" value="{{ $filters['action'] }}" placeholder="{{ __('settings.filter_action') }}"
           class="bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
    <input type="number" name="actor" value="{{ $filters['actor'] }}" placeholder="{{ __('settings.filter_actor_id') }}"
           class="bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
    <input type="date" name="from" value="{{ $filters['from'] }}"
           class="bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
    <input type="date" name="to" value="{{ $filters['to'] }}"
           class="bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
    <div class="md:col-span-4">
        <button class="px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('settings.filter') }}</button>
    </div>
</form>

<div class="bg-surface border border-th-border rounded-2xl p-6">
    @if($logs->isEmpty())
        <p class="text-[13px] text-muted">{{ __('settings.no_audit_logs') }}</p>
    @else
    <table class="w-full text-[13px]">
        <thead class="text-muted border-b border-th-border">
            <tr>
                <th class="text-start py-2">{{ __('settings.when') }}</th>
                <th class="text-start py-2">{{ __('settings.actor') }}</th>
                <th class="text-start py-2">{{ __('settings.action') }}</th>
                <th class="text-start py-2">{{ __('settings.target') }}</th>
                <th class="text-start py-2">IP</th>
            </tr>
        </thead>
        <tbody>
        @foreach($logs as $log)
            <tr class="border-b border-th-border/50">
                <td class="py-3 text-muted">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                <td class="py-3 text-primary">{{ $log->user?->full_name ?? '—' }}</td>
                <td class="py-3 font-mono text-primary">{{ $log->action }}</td>
                <td class="py-3 text-muted">{{ $log->entity_type }}#{{ $log->entity_id }}</td>
                <td class="py-3 text-muted font-mono">{{ $log->ip_address }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="mt-5">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
