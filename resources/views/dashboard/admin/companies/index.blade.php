@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.companies.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.companies.title')" :subtitle="__('admin.companies.subtitle')" />

@include('dashboard.admin._tabs', ['active' => 'companies'])

<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('admin.companies.total')"    color="blue" />
    <x-dashboard.stat-card :value="$stats['active']"   :label="__('admin.companies.active')"   color="green" />
    <x-dashboard.stat-card :value="$stats['pending']"  :label="__('admin.companies.pending')"  color="orange" />
    <x-dashboard.stat-card :value="$stats['inactive']" :label="__('admin.companies.inactive')" color="red" />
</div>

<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search_placeholder') }}"
           class="md:col-span-2 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent" />
    <select name="status" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.companies.all_statuses') }}</option>
        @foreach(\App\Enums\CompanyStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($status === $s->value)>{{ __('status.' . $s->value) }}</option>
        @endforeach
    </select>
    <select name="type" class="bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
        <option value="">{{ __('admin.companies.all_types') }}</option>
        @foreach(\App\Enums\CompanyType::cases() as $t)
            <option value="{{ $t->value }}" @selected($type === $t->value)>{{ __('role.' . $t->value) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-accent text-white rounded-lg px-4 py-2 text-[13px] font-semibold">{{ __('common.filter') }}</button>
</form>

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2 text-faint text-[11px] uppercase tracking-wider">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('admin.companies.name') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.companies.type') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.companies.location') }}</th>
                    <th class="text-start px-4 py-3">{{ __('admin.companies.users_count') }}</th>
                    <th class="text-start px-4 py-3">{{ __('common.status') }}</th>
                    <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($companies as $c)
                <tr class="hover:bg-surface-2/50">
                    <td class="px-4 py-3">
                        <p class="text-primary font-semibold">{{ $c->name }}</p>
                        <p class="text-[11px] text-muted">{{ $c->registration_number }}</p>
                    </td>
                    <td class="px-4 py-3 text-body">{{ __('role.' . ($c->type?->value ?? 'buyer')) }}</td>
                    <td class="px-4 py-3 text-body">{{ trim(($c->city ?? '') . ($c->city && $c->country ? ', ' : '') . ($c->country ?? '')) ?: '—' }}</td>
                    <td class="px-4 py-3 text-body">{{ $c->users_count }}</td>
                    <td class="px-4 py-3"><x-dashboard.status-badge :status="$c->status?->value ?? 'pending'" /></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @if($c->status?->value === 'pending')
                            <form method="POST" action="{{ route('admin.companies.approve', $c->id) }}" class="inline">@csrf
                                <button type="submit" class="px-2 py-1 rounded bg-emerald-500/10 text-emerald-400 text-[11px] font-semibold hover:bg-emerald-500/20">{{ __('admin.companies.approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.companies.reject', $c->id) }}" class="inline">@csrf
                                <button type="submit" class="px-2 py-1 rounded bg-red-500/10 text-red-400 text-[11px] font-semibold hover:bg-red-500/20">{{ __('admin.companies.reject') }}</button>
                            </form>
                            @endif
                            <a href="{{ route('admin.companies.show', $c->id) }}" class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary" title="{{ __('common.view_details') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('admin.companies.edit', $c->id) }}" class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary" title="{{ __('common.edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-8">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-th-border">{{ $companies->links() }}</div>
</div>

@endsection
