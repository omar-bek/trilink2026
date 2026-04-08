@extends('layouts.dashboard', ['active' => 'admin-audit'])
@section('title', __('admin.audit'))

@section('content')

<x-dashboard.page-header :title="__('admin.audit')" :subtitle="__('admin.audit_subtitle')">
    <x-slot:actions>
        <a href="{{ route('admin.audit.export', request()->query()) }}"
           class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[13px] font-bold text-primary bg-surface-2 border border-th-border hover:bg-surface transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            {{ __('admin.audit_export') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

<x-admin.navbar active="audit" />

{{-- ─────────────────────── Filter bar ─────────────────────── --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-3">
        <select name="action" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('admin.audit_action') }}</option>
            @foreach($actions as $a)
                <option value="{{ $a->value }}" @selected($action === $a->value)>{{ $a->value }}</option>
            @endforeach
        </select>
        <select name="resource" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('admin.audit_resource') }}</option>
            @foreach($resources as $r)
                <option value="{{ $r }}" @selected($resource === $r)>{{ $r }}</option>
            @endforeach
        </select>
        <input type="number" name="user_id" value="{{ $userId }}" placeholder="{{ __('admin.audit_user_id') }}"
               class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent" />
        <input type="text" name="ip" value="{{ $ip ?? '' }}" placeholder="{{ __('admin.audit_ip') }}"
               class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint font-mono focus:outline-none focus:border-accent" />
        <input type="text" name="ua" value="{{ $userAgent ?? '' }}" placeholder="{{ __('admin.audit_user_agent') }}"
               class="md:col-span-2 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent" />
        <input type="date" name="from" value="{{ $from }}" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <input type="date" name="to" value="{{ $to }}" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
    <div class="mt-3 flex items-center justify-end">
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 bg-accent text-white rounded-[12px] px-5 h-11 text-[13px] font-bold hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            {{ __('common.filter') }}
        </button>
    </div>
</form>

{{-- ─────────────────────── Audit log table ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.audit_when') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.audit_user') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.audit_action') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.audit_resource') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.audit_ip') }}</th>
                    <th class="text-end px-5 py-4 font-bold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($logs as $log)
                @php
                    // Color-code action verbs at a glance: green = create, blue = update,
                    // pink = delete, slate = everything else (read/login/etc).
                    $actionVal   = $log->action?->value ?? (string) $log->action;
                    $actionCls = match (true) {
                        str_contains($actionVal, 'create') => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30',
                        str_contains($actionVal, 'update') => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/30',
                        str_contains($actionVal, 'delete') => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30',
                        str_contains($actionVal, 'login')  => 'bg-[#8B5CF6]/10 text-[#8B5CF6] border-[#8B5CF6]/30',
                        default                            => 'bg-surface-2 text-muted border-th-border',
                    };
                @endphp
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="px-5 py-4 text-[11px] text-muted font-mono whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="px-5 py-4">
                        <p class="text-primary font-semibold">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }}</p>
                        @if($log->user?->email)
                        <p class="text-[11px] text-muted truncate">{{ $log->user->email }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $actionCls }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            {{ $actionVal }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-body font-mono text-[11px]">{{ $log->resource_type }}<span class="text-faint">#</span>{{ $log->resource_id }}</td>
                    <td class="px-5 py-4 text-[11px] text-muted font-mono">{{ $log->ip_address }}</td>
                    <td class="px-5 py-4 text-end">
                        <a href="{{ route('admin.audit.show', $log->id) }}"
                           class="inline-flex items-center gap-1 text-accent text-[12px] font-semibold hover:underline">
                            {{ __('common.view_details') }}
                            <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $logs->links() }}</div>
</div>

@endsection
