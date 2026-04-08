@extends('layouts.dashboard', ['active' => 'admin-companies'])
@section('title', __('admin.companies.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.companies.title')" :subtitle="__('admin.companies.subtitle')" />

<x-admin.navbar active="companies" />

{{-- ─────────────────────── Stats — clickable status filters ─────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('admin.companies.total')"    color="blue"   :href="route('admin.companies.index')" :active="$status === ''" />
    <x-dashboard.stat-card :value="$stats['active']"   :label="__('admin.companies.active')"   color="green"  :href="route('admin.companies.index', ['status' => 'active'])"   :active="$status === 'active'" />
    <x-dashboard.stat-card :value="$stats['pending']"  :label="__('admin.companies.pending')"  color="orange" :href="route('admin.companies.index', ['status' => 'pending'])"  :active="$status === 'pending'" />
    <x-dashboard.stat-card :value="$stats['inactive']" :label="__('admin.companies.inactive')" color="red"    :href="route('admin.companies.index', ['status' => 'inactive'])" :active="$status === 'inactive'" />
</div>

{{-- ─────────────────────── Filter bar ─────────────────────── --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-6 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
    <div class="md:col-span-5 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search_placeholder') }}"
               class="w-full bg-surface-2 border border-th-border rounded-[12px] ps-11 pe-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
    </div>
    <select name="status" class="md:col-span-3 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
        <option value="">{{ __('admin.companies.all_statuses') }}</option>
        @foreach(\App\Enums\CompanyStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($status === $s->value)>{{ __('status.' . $s->value) }}</option>
        @endforeach
    </select>
    <select name="type" class="md:col-span-2 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary focus:outline-none focus:border-accent">
        <option value="">{{ __('admin.companies.all_types') }}</option>
        @foreach(\App\Enums\CompanyType::cases() as $t)
            <option value="{{ $t->value }}" @selected($type === $t->value)>{{ __('role.' . $t->value) }}</option>
        @endforeach
    </select>
    <button type="submit"
            class="md:col-span-2 inline-flex items-center justify-center gap-2 bg-accent text-white rounded-[12px] px-4 h-11 text-[13px] font-bold hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        {{ __('common.filter') }}
    </button>
</form>

<form method="POST" action="{{ route('admin.companies.bulk-rescreen') }}"
      x-data="{ count: 0 }"
      @change="count = $el.querySelectorAll('input[name=\'ids[]\']:checked').length">
    @csrf

    {{-- ─────────────────────── Bulk action toolbar ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] px-[17px] py-3 mb-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                <svg class="w-4 h-4 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-[13px] font-semibold text-primary"><span x-text="count">0</span> {{ __('admin.companies.bulk_selected_suffix') }}</p>
                <p class="text-[11px] text-muted">{{ __('admin.companies.bulk_rescreen') }}</p>
            </div>
        </div>
        <button type="submit"
                x-bind:disabled="count === 0"
                x-bind:class="count === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                class="inline-flex items-center gap-2 px-5 h-11 rounded-[12px] text-[13px] font-bold text-white bg-[#ffb020] hover:brightness-110 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0115.6-6.2M21 12a9 9 0 01-15.6 6.2M21 3v6h-6M3 21v-6h6"/></svg>
            {{ __('admin.companies.bulk_rescreen') }}
        </button>
    </div>

    {{-- ─────────────────────── Companies table ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-surface-2">
                    <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                        <th class="w-12 px-5 py-4"></th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.companies.name') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.companies.type') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.companies.location') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.companies.users_count') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('common.status') }}</th>
                        <th class="text-end px-5 py-4 font-bold">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-th-border">
                    @forelse($companies as $c)
                    @php
                        $palette = ['#4f7cff', '#00d9b5', '#8B5CF6', '#ffb020', '#ff4d7f', '#14B8A6'];
                        $brandColor = $palette[$c->id % count($palette)];
                    @endphp
                    <tr class="hover:bg-surface-2/50 transition-colors">
                        <td class="px-5 py-4">
                            <input type="checkbox" name="ids[]" value="{{ $c->id }}"
                                   class="w-4 h-4 rounded border-th-border bg-surface-2 text-[#ffb020] focus:ring-[#ffb020]">
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-[10px] font-bold flex items-center justify-center text-[12px] flex-shrink-0"
                                     style="background: {{ $brandColor }}1a; color: {{ $brandColor }}; border: 1px solid {{ $brandColor }}33;">
                                    {{ strtoupper(substr($c->name ?? 'C', 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-primary font-semibold truncate">{{ $c->name }}</p>
                                    <p class="text-[11px] text-muted font-mono truncate">{{ $c->registration_number }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-body">{{ __('role.' . ($c->type?->value ?? 'buyer')) }}</td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-1.5 text-body">
                                @if($c->city || $c->country)
                                <svg class="w-3.5 h-3.5 text-muted flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="truncate">{{ trim(($c->city ?? '') . ($c->city && $c->country ? ', ' : '') . ($c->country ?? '')) }}</span>
                                @else
                                <span class="text-faint">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center gap-1 text-body">
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                {{ $c->users_count }}
                            </span>
                        </td>
                        <td class="px-5 py-4"><x-dashboard.status-badge :status="$c->status?->value ?? 'pending'" /></td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-1">
                                @if($c->status?->value === 'pending')
                                <form method="POST" action="{{ route('admin.companies.approve', $c->id) }}" class="inline">@csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 h-8 rounded-[8px] bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] text-[11px] font-bold hover:bg-[#00d9b5]/20">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        {{ __('admin.companies.approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.companies.reject', $c->id) }}" class="inline">@csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 h-8 rounded-[8px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[11px] font-bold hover:bg-[#ff4d7f]/20">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ __('admin.companies.reject') }}
                                    </button>
                                </form>
                                @endif
                                <a href="{{ route('admin.companies.show', $c->id) }}" title="{{ __('common.view_details') }}"
                                   class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-accent/10 hover:text-accent transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('admin.companies.edit', $c->id) }}" title="{{ __('common.edit') }}"
                                   class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#8B5CF6]/10 hover:text-[#8B5CF6] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/></svg>
                            </div>
                            <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $companies->links() }}</div>
    </div>
</form>

@endsection
