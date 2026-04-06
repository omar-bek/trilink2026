@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.users.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.users.title')" :subtitle="__('admin.users.subtitle')">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 bg-accent text-white px-4 py-2.5 rounded-lg text-[13px] font-semibold hover:opacity-90 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            {{ __('admin.users.new') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

@include('dashboard.admin._tabs', ['active' => 'users'])

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('admin.users.total')"    color="blue" />
    <x-dashboard.stat-card :value="$stats['active']"   :label="__('admin.users.active')"   color="green" />
    <x-dashboard.stat-card :value="$stats['pending']"  :label="__('admin.users.pending')"  color="orange" />
    <x-dashboard.stat-card :value="$stats['inactive']" :label="__('admin.users.inactive')" color="red" />
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search_placeholder') }}"
           class="md:col-span-2 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent" />
    <select name="role" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.users.all_roles') }}</option>
        @foreach(\App\Enums\UserRole::cases() as $r)
            <option value="{{ $r->value }}" @selected($role === $r->value)>{{ __('role.' . $r->value) }}</option>
        @endforeach
    </select>
    <select name="status" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.users.all_statuses') }}</option>
        @foreach(\App\Enums\UserStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($status === $s->value)>{{ __('status.' . $s->value) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-accent text-white rounded-lg px-4 py-2 text-[13px] font-semibold">{{ __('common.filter') }}</button>
</form>

{{-- Table --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2 text-faint text-[11px] uppercase tracking-wider">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('admin.users.name') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.users.email') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.users.role_col') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.users.company') }}</th>
                    <th class="text-start px-4 py-3">{{ __('common.status') }}</th>
                    <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($users as $u)
                <tr class="hover:bg-surface-2/50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-accent/10 text-accent font-bold flex items-center justify-center text-[11px]">
                                {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-primary font-semibold">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                                <p class="text-[11px] text-muted">{{ $u->phone ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-body">{{ $u->email }}</td>
                    <td class="px-4 py-3"><span class="text-[11px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span></td>
                    <td class="px-4 py-3 text-body">{{ $u->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3"><x-dashboard.status-badge :status="$u->status?->value ?? 'pending'" /></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('admin.users.edit', $u->id) }}" title="{{ __('common.edit') }}"
                               class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="inline">@csrf
                                <button type="submit" title="{{ __('admin.users.toggle') }}"
                                        class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 11-12.728 0M12 3v9"/></svg>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reset', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_reset') }}');">@csrf
                                <button type="submit" title="{{ __('admin.users.reset') }}"
                                        class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </button>
                            </form>
                            @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_delete') }}');">@csrf @method('DELETE')
                                <button type="submit" title="{{ __('common.delete') }}"
                                        class="p-1.5 rounded hover:bg-red-500/10 text-muted hover:text-red-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-8">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-th-border">{{ $users->links() }}</div>
</div>

@endsection
