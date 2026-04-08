@extends('layouts.dashboard', ['active' => 'company-users'])
@section('title', __('company.users.title'))

@section('content')

<x-dashboard.page-header :title="__('company.users.title')" :subtitle="__('company.users.subtitle')">
    @can('team.invite')
    <x-slot:actions>
        <a href="{{ route('company.users.create') }}" class="inline-flex items-center gap-2 bg-accent text-white h-11 px-5 rounded-xl text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
            {{ __('company.users.invite') }}
        </a>
    </x-slot:actions>
    @endcan
</x-dashboard.page-header>

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">{{ session('status') }}</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="md:col-span-3 relative">
        <svg class="w-4 h-4 text-faint absolute start-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search_placeholder') }}"
               class="w-full bg-surface-2 border border-th-border rounded-lg ps-10 pe-3 py-2.5 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent" />
    </div>
    <button type="submit" class="inline-flex items-center justify-center gap-2 bg-accent text-white rounded-lg px-4 py-2.5 text-[13px] font-semibold hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
        {{ __('common.filter') }}
    </button>
</form>

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2 text-faint text-[11px] uppercase tracking-wider">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('admin.users.name') }}</th>
                    <th class="text-start px-4 py-3">{{ __('company.users.position') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.users.role_col') }}</th>
                    <th class="text-start px-4 py-3">{{ __('company.users.permissions_count') }}</th>
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
                                <p class="text-[11px] text-muted">{{ $u->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-body">{{ $u->position_title ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-[11px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span>
                        @foreach((array) ($u->additional_roles ?? []) as $extra)
                            <span class="text-[10px] text-muted bg-surface-2 border border-th-border rounded-full px-2 py-0.5 ms-1">+{{ __('role.' . $extra) }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-body">{{ count((array) ($u->permissions ?? [])) }}</td>
                    <td class="px-4 py-3"><x-dashboard.status-badge :status="$u->status?->value ?? 'pending'" /></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @can('team.edit')
                            <a href="{{ route('company.users.edit', $u->id) }}" title="{{ __('common.edit') }}"
                               class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @endcan
                            @if($u->id !== auth()->id())
                            @can('team.edit')
                            <form method="POST" action="{{ route('company.users.toggle', $u->id) }}" class="inline">@csrf
                                <button type="submit" title="{{ __('admin.users.toggle') }}"
                                        class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 11-12.728 0M12 3v9"/></svg>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('company.users.reset', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_reset') }}');">@csrf
                                <button type="submit" title="{{ __('admin.users.reset') }}"
                                        class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </button>
                            </form>
                            @endcan
                            @can('team.remove')
                            <form method="POST" action="{{ route('company.users.destroy', $u->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.users.confirm_delete') }}');">@csrf @method('DELETE')
                                <button type="submit" title="{{ __('common.delete') }}"
                                        class="p-1.5 rounded hover:bg-red-500/10 text-muted hover:text-red-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        </div>
                        <p class="text-[14px] font-bold text-primary">{{ __('company.users.no_users') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-th-border">{{ $users->links() }}</div>
</div>

@endsection
