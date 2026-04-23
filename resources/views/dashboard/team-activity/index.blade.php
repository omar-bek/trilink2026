@extends('layouts.dashboard', ['active' => 'team-activity'])
@section('title', __('team_activity.title'))

@section('content')

<x-dashboard.page-header :title="__('team_activity.title')" :subtitle="__('team_activity.subtitle')">
    <x-slot:actions>
        <a href="{{ route('dashboard.team-activity.export', request()->query()) }}"
           class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[13px] font-bold text-primary bg-surface-2 border border-th-border hover:bg-surface transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            {{ __('team_activity.export') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

{{-- ─────────────────────── Filter bar ─────────────────────── --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <select name="user_id" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('team_activity.all_members') }}</option>
            @foreach($teamMembers as $member)
                <option value="{{ $member->id }}" @selected((string) $userId === (string) $member->id)>
                    {{ trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: $member->email }}
                </option>
            @endforeach
        </select>
        <select name="action" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('team_activity.all_actions') }}</option>
            @foreach($actions as $a)
                <option value="{{ $a->value }}" @selected($action === $a->value)>{{ $a->value }}</option>
            @endforeach
        </select>
        <select name="resource" class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
            <option value="">{{ __('team_activity.all_resources') }}</option>
            @foreach($resources as $r)
                <option value="{{ $r }}" @selected($resource === $r)>{{ $r }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" placeholder="{{ __('team_activity.from') }}"
               class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <input type="date" name="to" value="{{ $to }}" placeholder="{{ __('team_activity.to') }}"
               class="bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent" />
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 bg-accent text-white rounded-[12px] px-5 h-11 text-[13px] font-bold hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            {{ __('common.filter') }}
        </button>
    </div>
</form>

{{-- ─────────────────────── Activity table ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="text-start px-5 py-4 font-bold">{{ __('team_activity.when') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('team_activity.member') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('team_activity.action') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('team_activity.resource') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('team_activity.ip') }}</th>
                    <th class="text-end px-5 py-4 font-bold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($logs as $log)
                @php
                    $actionVal = $log->action?->value ?? (string) $log->action;
                    $actionCls = match (true) {
                        str_contains($actionVal, 'create') => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30',
                        str_contains($actionVal, 'update') => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/30',
                        str_contains($actionVal, 'delete') => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30',
                        str_contains($actionVal, 'login')  => 'bg-[#8B5CF6]/10 text-[#8B5CF6] border-[#8B5CF6]/30',
                        str_contains($actionVal, 'approve') || str_contains($actionVal, 'sign') => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30',
                        str_contains($actionVal, 'reject') => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30',
                        default                            => 'bg-surface-2 text-muted border-th-border',
                    };
                    $roleLabel = $log->user?->role instanceof \BackedEnum ? $log->user->role->value : ($log->user?->role ?? '');
                @endphp
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="px-5 py-4 text-[11px] text-muted font-mono whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="px-5 py-4">
                        <p class="text-primary font-semibold">{{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: '—' }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[11px] text-muted truncate">{{ $log->user?->email ?? '' }}</span>
                            @if($roleLabel)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold text-muted bg-surface-2 border border-th-border">{{ $roleLabel }}</span>
                            @endif
                        </div>
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
                        <a href="{{ route('dashboard.team-activity.show', $log->id) }}"
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
                        <p class="text-[13px] text-muted">{{ __('team_activity.empty') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $logs->links() }}</div>
</div>

@endsection
