@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', 'PR Details')

@section('content')

<x-dashboard.page-header
    :title="$pr['title']"
    :subtitle="$pr['id'] . ' · ' . __('status.' . $pr['status'])"
    :back="route('dashboard.purchase-requests')"
/>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Header info --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.department') }}</p>
                    <p class="text-[14px] font-bold text-primary">{{ $pr['department'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.estimated_budget') }}</p>
                    <p class="text-[14px] font-bold text-[#10B981]">{{ $pr['budget'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.expected_delivery') }}</p>
                    <p class="text-[14px] font-bold text-primary">{{ $pr['delivery'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.delivery_location') }}</p>
                    <p class="text-[14px] font-bold text-primary">{{ $pr['location'] }}</p>
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('pr.line_items') }}</h3>
            <div class="space-y-3">
                @foreach($pr['items'] as $item)
                <div class="bg-page border border-th-border rounded-xl p-5">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <span class="text-[11px] text-muted">Item #{{ $item['n'] }}</span>
                        <span class="text-[11px] text-muted">Estimated Price</span>
                    </div>
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <p class="text-[15px] font-bold text-primary">{{ $item['name'] }}</p>
                        <p class="text-[16px] font-bold text-[#10B981]">{{ $item['price'] }}</p>
                    </div>
                    <p class="text-[12px] text-muted mb-2">{{ $item['desc'] }}</p>
                    <p class="text-[12px] text-muted">{{ __('pr.quantity') }}: <span class="font-semibold text-body">{{ $item['qty'] }}</span></p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Related RFQs --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[16px] font-bold text-primary">{{ __('pr.related_rfqs') }}</h3>
                <span class="text-[12px] text-muted">{{ __('pr.rfqs_created', ['count' => count($pr['related_rfqs'])]) }}</span>
            </div>
            <div class="space-y-3">
                @forelse($pr['related_rfqs'] as $rfq)
                <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}" class="block bg-page border border-th-border rounded-xl p-4 hover:bg-surface-2 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-[11px] font-mono text-muted">{{ $rfq['id'] }}</span>
                            <x-dashboard.status-badge :status="$rfq['status']" />
                            <span class="text-[10px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ $rfq['tag'] }}</span>
                        </div>
                        <div class="text-end">
                            <p class="text-[20px] font-bold text-primary">{{ $rfq['bids'] }}</p>
                            <p class="text-[10px] text-muted">{{ __('pr.bids_received') }}</p>
                        </div>
                    </div>
                    <p class="text-[14px] font-bold text-accent mb-2">{{ $rfq['title'] }}</p>
                    <div class="flex items-center gap-4 text-[11px] text-muted">
                        <span>{{ __('pr.created') }} {{ $rfq['created'] }}</span>
                        @if($rfq['deadline'])<span>{{ __('pr.deadline') }} {{ $rfq['deadline'] }}</span>@endif
                    </div>
                </a>
                @empty
                <p class="text-[12px] text-muted">{{ __('pr.no_rfqs_yet') }}</p>
                @endforelse
            </div>
        </div>

        @if($pr['description'])
        {{-- Description / Special Instructions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-3">{{ __('pr.description') }}</h3>
            <p class="text-[13px] text-muted leading-relaxed">{{ $pr['description'] }}</p>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-5">{{ __('contracts.timeline') }}</h3>
            <div class="space-y-5">
                @forelse($pr['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)
                    <div class="absolute start-3 top-6 w-0.5 h-full bg-th-border"></div>
                    @endif
                    <div class="w-6 h-6 rounded-full {{ $event['done'] ? 'bg-[#10B981]' : 'bg-surface-2 border border-th-border' }} flex items-center justify-center flex-shrink-0 z-10">
                        @if($event['done'])
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                        @else
                        <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 pb-4">
                        <p class="text-[13px] font-semibold text-primary">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted">{{ $event['who'] }}</p>
                        <p class="text-[10px] text-faint">{{ $event['when'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
