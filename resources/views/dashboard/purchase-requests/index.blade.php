@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.title'))

@section('content')

<x-dashboard.page-header :title="__('pr.title')" :subtitle="__('pr.subtitle')">
    @can('pr.create')
    <x-slot:actions>
        <a href="{{ route('dashboard.purchase-requests.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('pr.new') }}
        </a>
    </x-slot:actions>
    @endcan
</x-dashboard.page-header>

{{-- Search bar --}}
<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="{{ __('pr.search_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40 transition-colors" />
    </div>
    <div class="flex-shrink-0 w-full lg:w-[200px]">
        <select class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/40 cursor-pointer">
            <option value="">{{ __('common.status') }}</option>
            <option value="draft">{{ __('status.draft') }}</option>
            <option value="pending">{{ __('status.pending') }}</option>
            <option value="approved">{{ __('status.approved') }}</option>
        </select>
    </div>
    <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-medium text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
        {{ __('pr.more_filters') }}
    </button>
</div>

{{-- Stats grid --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('pr.total')"            color="purple" />
    <x-dashboard.stat-card :value="$stats['pending']"  :label="__('pr.pending_approval')" color="orange" />
    <x-dashboard.stat-card :value="$stats['approved']" :label="__('pr.approved')"         color="green" />
    <x-dashboard.stat-card :value="$stats['progress']" :label="__('pr.in_progress')"      color="blue" />
    <x-dashboard.stat-card :value="$stats['closed']"   :label="__('pr.closed')"           color="slate" />
</div>

{{-- List --}}
<div class="space-y-4">
    @foreach($requests as $r)
    <a href="{{ route('dashboard.purchase-requests.show', ['id' => $r['id']]) }}" class="block bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-lg transition-all">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">{{ $r['id'] }}</span>
                    <x-dashboard.status-badge :status="$r['status']" />
                    <span class="text-[11px] font-medium text-muted bg-surface-2 border border-th-border rounded-full px-2.5 py-0.5">{{ $r['tag'] }}</span>
                </div>
                <h3 class="text-[18px] font-bold text-accent mb-1">{{ $r['title'] }}</h3>
                <p class="text-[13px] text-muted">{{ $r['desc'] }}</p>
            </div>
            <div class="text-end flex-shrink-0">
                <p class="text-[22px] font-bold text-[#10B981]">{{ $r['amount'] }}</p>
                <div class="flex items-center justify-end gap-3 mt-1 text-[11px] text-muted">
                    <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>{{ $r['rfqs'] }} RFQs</span>
                    <span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 3h1.386c.51 0 .955.343 1.087.835"/></svg>{{ $r['bids'] }} Bids</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 text-[12px] text-muted">
            @if($r['status'] === 'approved')
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                {{ __('pr.created_by') }} {{ $r['creator'] }}
            </span>
            @elseif($r['status'] === 'pending')
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#F59E0B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                {{ __('pr.created_by') }} {{ $r['creator'] }}
            </span>
            @elseif($r['status'] === 'closed')
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#EF4444]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6m-6 0l6-6"/></svg>
                {{ __('pr.created_by') }} {{ $r['creator'] }}
            </span>
            @else
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
                {{ __('pr.created_by') }} {{ $r['creator'] }}
            </span>
            @endif
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                {{ $r['date'] }}
            </span>
        </div>

        @if(!empty($r['progress']))
        <div class="mt-5">
            <div class="flex items-center justify-between text-[11px] text-muted mb-1.5">
                <span>{{ __('pr.rfq_creation_progress') }}</span>
                <span>{{ $r['progress']['done'] }} / {{ $r['progress']['total'] }} Created</span>
            </div>
            <div class="w-full h-2 bg-elevated rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-[#10B981] to-[#3B82F6] rounded-full" style="width: {{ ($r['progress']['done'] / $r['progress']['total']) * 100 }}%"></div>
            </div>
        </div>
        @endif
    </a>
    @endforeach
</div>

@endsection
