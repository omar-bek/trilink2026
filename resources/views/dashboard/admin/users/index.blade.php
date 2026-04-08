@extends('layouts.dashboard', ['active' => 'admin-users'])
@section('title', __('admin.users.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.users.title')" :subtitle="__('admin.users.subtitle')">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center gap-2 h-12 px-5 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            {{ __('admin.users.new') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

<x-admin.navbar active="users" />

{{-- ─────────────────────── Stats — clickable status filters ─────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('admin.users.total')"    color="blue"   :href="route('admin.users.index')" :active="$status === ''" />
    <x-dashboard.stat-card :value="$stats['active']"   :label="__('admin.users.active')"   color="green"  :href="route('admin.users.index', ['status' => 'active'])"   :active="$status === 'active'" />
    <x-dashboard.stat-card :value="$stats['pending']"  :label="__('admin.users.pending')"  color="orange" :href="route('admin.users.index', ['status' => 'pending'])"  :active="$status === 'pending'" />
    <x-dashboard.stat-card :value="$stats['inactive']" :label="__('admin.users.inactive')" color="red"    :href="route('admin.users.index', ['status' => 'inactive'])" :active="$status === 'inactive'" />
</div>

{{-- ─────────────────────── Filter bar — search + role + status ─────────────────────── --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-6 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
    <div class="md:col-span-5 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search_placeholder') }}"
               class="w-full bg-surface-2 border border-th-border rounded-[12px] ps-11 pe-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
    </div>
    <select name="role" class="md:col-span-3 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
        <option value="">{{ __('admin.users.all_roles') }}</option>
        @foreach(\App\Enums\UserRole::cases() as $r)
            <option value="{{ $r->value }}" @selected($role === $r->value)>{{ __('role.' . $r->value) }}</option>
        @endforeach
    </select>
    <select name="status" class="md:col-span-2 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
        <option value="">{{ __('admin.users.all_statuses') }}</option>
        @foreach(\App\Enums\UserStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($status === $s->value)>{{ __('status.' . $s->value) }}</option>
        @endforeach
    </select>
    <button type="submit"
            class="md:col-span-2 inline-flex items-center justify-center gap-2 bg-accent text-white rounded-[12px] px-4 h-11 text-[13px] font-bold hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        {{ __('common.filter') }}
    </button>
</form>

{{-- ─────────────────────── Users table ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.users.name') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.users.email') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.users.role_col') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.users.company') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('common.status') }}</th>
                    <th class="text-end px-5 py-4 font-bold">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($users as $u)
                @php
                    // Deterministic avatar tint based on user id so the same person
                    // is always the same color across sessions.
                    $avatarPalette = ['#4f7cff', '#00d9b5', '#8B5CF6', '#ffb020', '#ff4d7f', '#14B8A6'];
                    $avatarColor   = $avatarPalette[$u->id % count($avatarPalette)];
                @endphp
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full font-bold flex items-center justify-center text-[12px] flex-shrink-0"
                                 style="background: {{ $avatarColor }}1a; color: {{ $avatarColor }}; border: 1px solid {{ $avatarColor }}33;">
                                {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-primary font-semibold truncate">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: '—' }}</p>
                                <p class="text-[11px] text-muted truncate">{{ $u->phone ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-body">{{ $u->email }}</td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold text-accent bg-accent/10 border border-accent/20 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                            {{ __('role.' . ($u->role?->value ?? 'buyer')) }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-body">{{ $u->company?->name ?? '—' }}</td>
                    <td class="px-5 py-4"><x-dashboard.status-badge :status="$u->status?->value ?? 'pending'" /></td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('admin.users.edit', $u->id) }}" title="{{ __('common.edit') }}"
                               class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-accent/10 hover:text-accent transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="inline">@csrf
                                <button type="submit" title="{{ __('admin.users.toggle') }}"
                                        class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#ffb020]/10 hover:text-[#ffb020] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 11-12.728 0M12 3v9"/></svg>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reset', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_reset') }}');">@csrf
                                <button type="submit" title="{{ __('admin.users.reset') }}"
                                        class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#8B5CF6]/10 hover:text-[#8B5CF6] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </button>
                            </form>
                            @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_delete') }}');">@csrf @method('DELETE')
                                <button type="submit" title="{{ __('common.delete') }}"
                                        class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#ff4d7f]/10 hover:text-[#ff4d7f] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $users->links() }}</div>
</div>

@endsection
