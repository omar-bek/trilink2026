@extends('layouts.dashboard', ['active' => 'gov'])
@section('title', __('gov.contracts_title'))

@section('content')

<x-dashboard.page-header :title="__('gov.contracts_title')" :subtitle="__('gov.contracts_subtitle')" :back="route('gov.index')" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="number_format($stats['total'])" :label="__('gov.total_contracts')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['active'])" :label="__('gov.active_contracts')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' />
    <x-dashboard.stat-card :value="'AED ' . number_format($stats['value'])" :label="__('gov.active_gmv')" color="purple"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4"/>' />
    <x-dashboard.stat-card :value="number_format($stats['signed'])" :label="__('gov.pending_signature')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/>' />
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-wrap gap-3">
    <input name="q" value="{{ request('q') }}" placeholder="{{ __('gov.search_contract') }}" class="flex-1 min-w-[200px] bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
    <select name="status" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
        <option value="">{{ __('common.all_statuses') }}</option>
        @foreach(['draft','pending_signatures','signed','active','completed','cancelled','terminated'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-5 h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
    <a href="{{ route('gov.export', ['type' => 'contracts']) }}" class="px-4 h-10 rounded-xl text-[12px] font-semibold border border-th-border text-primary bg-surface hover:bg-surface-2 inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        CSV
    </a>
</form>

{{-- Contracts list --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <table class="w-full text-[13px]">
        <thead>
            <tr class="border-b border-th-border bg-surface-2">
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('contracts.number') }}</th>
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.buyer') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.amount') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contracts as $c)
            @php
                $statusColors = ['draft'=>'#525252','pending_signatures'=>'#ffb020','signed'=>'#4f7cff','active'=>'#00d9b5','completed'=>'#00d9b5','cancelled'=>'#ff4d7f','terminated'=>'#ef4444'];
                $sc = $statusColors[$c->status?->value ?? 'draft'] ?? '#525252';
            @endphp
            <tr class="border-b border-th-border/50 hover:bg-surface-2">
                <td class="py-3 px-4 font-mono font-semibold text-primary">{{ $c->contract_number ?? '#'.$c->id }}</td>
                <td class="py-3 px-4 text-primary">{{ $c->buyerCompany?->name ?? '—' }}</td>
                <td class="py-3 px-4 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:{{ $sc }}1A;color:{{ $sc }};">{{ ucfirst(str_replace('_',' ',$c->status?->value ?? 'draft')) }}</span></td>
                <td class="py-3 px-4 text-right font-semibold text-primary">{{ $c->currency ?? 'AED' }} {{ number_format($c->total_amount, 2) }}</td>
                <td class="py-3 px-4 text-right text-muted">{{ $c->created_at?->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="py-12 text-center text-[14px] text-muted">{{ __('gov.no_contracts') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $contracts->links() }}</div>

@endsection
