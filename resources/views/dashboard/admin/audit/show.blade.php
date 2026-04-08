@extends('layouts.dashboard', ['active' => 'admin-audit'])
@section('title', __('admin.audit'))

@section('content')

<x-dashboard.page-header :title="__('admin.audit') . ' #' . $log->id" :back="route('admin.audit.index')" />

<x-admin.navbar active="audit" />

@php
    $actionVal   = $log->action?->value ?? (string) $log->action;
    $actionCls = match (true) {
        str_contains($actionVal, 'create') => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30',
        str_contains($actionVal, 'update') => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/30',
        str_contains($actionVal, 'delete') => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30',
        str_contains($actionVal, 'login')  => 'bg-[#8B5CF6]/10 text-[#8B5CF6] border-[#8B5CF6]/30',
        default                            => 'bg-surface-2 text-muted border-th-border',
    };

    $before = is_array($log->before) ? $log->before : [];
    $after  = is_array($log->after) ? $log->after : [];
    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    sort($keys);
    $stringify = static function ($v) {
        if ($v === null) { return '—'; }
        if (is_scalar($v)) { return (string) $v; }
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    };
@endphp

<div class="space-y-6 max-w-5xl">
    {{-- ─────────────────────── Summary card ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-[12px] bg-accent/10 border border-accent/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h3 class="text-[15px] font-bold text-primary leading-tight">{{ __('admin.audit') }} #{{ $log->id }}</h3>
                <p class="text-[11px] text-muted">{{ $log->created_at?->format('F j, Y · g:i:s A') }}</p>
            </div>
            <div class="ms-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold border {{ $actionCls }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ $actionVal }}
                </span>
            </div>
        </div>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-[13px]">
            <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.audit_when') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd></div>
            <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.audit_user') }}</dt><dd class="text-primary mt-1.5">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }} <span class="text-muted text-[12px]">({{ $log->user?->email ?? '—' }})</span></dd></div>
            <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.audit_resource') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $log->resource_type }}<span class="text-faint">#</span>{{ $log->resource_id }}</dd></div>
            <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.audit_ip') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $log->ip_address }}</dd></div>
            <div class="md:col-span-2"><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">User Agent</dt><dd class="text-body mt-1.5 text-[11px] font-mono break-all bg-surface-2 border border-th-border rounded-[10px] px-3 py-2">{{ $log->user_agent }}</dd></div>
        </dl>
    </div>

    {{-- ─────────────────────── Diff viewer ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('admin.audit_diff') }}</h3>
        </div>

        @if(empty($keys))
        <div class="rounded-[12px] border border-dashed border-th-border bg-surface-2/40 px-6 py-8 text-center">
            <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
        </div>
        @else
        <div class="bg-surface-2 border border-th-border rounded-[12px] overflow-hidden">
            <table class="w-full text-[12px]">
                <thead class="bg-surface">
                    <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                        <th class="text-start px-4 py-3 w-48 font-bold">{{ __('common.field') }}</th>
                        <th class="text-start px-4 py-3 font-bold">{{ __('admin.audit_before') }}</th>
                        <th class="text-start px-4 py-3 font-bold">{{ __('admin.audit_after') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-th-border">
                    @foreach($keys as $k)
                        @php
                            $bVal = $stringify($before[$k] ?? null);
                            $aVal = $stringify($after[$k] ?? null);
                            $changed = $bVal !== $aVal;
                        @endphp
                        <tr class="{{ $changed ? 'bg-[#ffb020]/[0.06]' : '' }}">
                            <td class="px-4 py-3 font-mono text-muted align-top text-[11px]">{{ $k }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="font-mono text-[11px] {{ $changed ? 'text-[#ff4d7f] line-through' : 'text-body' }} break-all">{{ $bVal }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="font-mono text-[11px] {{ $changed ? 'text-[#00d9b5] font-semibold' : 'text-body' }} break-all">{{ $aVal }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ─────────────────────── Raw JSON pair ─────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-9 h-9 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('admin.audit_before') }} <span class="text-muted text-[11px]">(JSON)</span></h3>
            </div>
            <pre class="bg-surface-2 border border-th-border rounded-[12px] p-3 text-[11px] text-body overflow-x-auto font-mono leading-relaxed">{{ $log->before ? json_encode($log->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '—' }}</pre>
        </div>
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('admin.audit_after') }} <span class="text-muted text-[11px]">(JSON)</span></h3>
            </div>
            <pre class="bg-surface-2 border border-th-border rounded-[12px] p-3 text-[11px] text-body overflow-x-auto font-mono leading-relaxed">{{ $log->after ? json_encode($log->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '—' }}</pre>
        </div>
    </div>

    {{-- ─────────────────────── Tamper-proof hash ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-[14px] font-bold text-primary">{{ __('admin.audit_hash') }}</h3>
        </div>
        <p class="text-[11px] text-muted font-mono break-all bg-surface-2 border border-th-border rounded-[10px] px-3 py-2.5">{{ $log->hash }}</p>
    </div>
</div>

@endsection
