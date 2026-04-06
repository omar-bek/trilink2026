@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('rfq.details'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <a href="{{ route('dashboard.rfqs') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back') }} to RFQs
        </a>
        <p class="text-[12px] font-mono text-muted mb-2">#{{ $rfq['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary leading-tight">{{ $rfq['title'] }}</h1>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <x-dashboard.status-badge :status="$rfq['status']" />
            <span class="text-[13px] text-muted">{{ __('rfq.published_on', ['date' => $rfq['published']]) }}</span>
            <span class="text-faint">·</span>
            <span class="text-[13px] text-muted">{{ __('rfq.bids_received', ['count' => $rfq['bids_count']]) }}</span>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
            {{ __('rfq.edit') }}
        </button>
        <button class="w-10 h-10 rounded-xl bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- RFQ Details --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[18px] font-bold text-primary mb-5">{{ __('rfq.details') }}</h3>

            @if($rfq['description'])
            <div class="mb-5">
                <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.description') }}</p>
                <p class="text-[14px] text-body leading-relaxed">{{ $rfq['description'] }}</p>
            </div>
            @endif

            <div class="grid grid-cols-2 gap-5 mb-5">
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.category') }}</p>
                    <span class="inline-block text-[11px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2.5 py-1 font-semibold">{{ $rfq['category'] }}</span>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.quantity') }}</p>
                    <p class="text-[14px] font-semibold text-primary">{{ $rfq['quantity'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        {{ __('pr.delivery_location') }}
                    </p>
                    <p class="text-[14px] font-semibold text-primary">{{ $rfq['location'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                        {{ __('rfq.delivery_deadline') }}
                    </p>
                    <p class="text-[14px] font-semibold text-primary">{{ $rfq['deadline'] ?: '—' }}</p>
                </div>
            </div>

            @if(!empty($rfq['tech_specs']))
            <div class="mb-5">
                <p class="text-[11px] text-muted uppercase tracking-wider mb-2">{{ __('rfq.tech_specs') }}</p>
                <ul class="space-y-1.5 text-[13px] text-body">
                    @foreach($rfq['tech_specs'] as $spec)
                    <li>• {{ $spec }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(!empty($rfq['attachments']))
            <div class="pt-5 border-t border-th-border">
                <p class="text-[11px] text-muted uppercase tracking-wider mb-3">{{ __('rfq.attachments') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($rfq['attachments'] as $file)
                    <a href="{{ $file['url'] ?? '#' }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-page border border-th-border hover:bg-surface-2 transition-colors">
                        <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
                        <span class="text-[12px] font-medium text-body">{{ $file['name'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Bids Received --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[18px] font-bold text-primary">{{ __('rfq.bids_received_section', ['count' => $rfq['bids_count']]) }}</h3>
                @if($rfq['bids_count'] > 0)
                <a href="{{ route('dashboard.rfqs.compare', ['id' => $rfq['numeric_id']]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">
                    {{ __('rfq.compare_bids') }}
                </a>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($rfq['bids'] as $bid)
                <div class="bg-page border {{ $bid['recommended'] ? 'border-accent/30' : 'border-th-border' }} rounded-xl p-4 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-lg bg-accent/10 flex items-center justify-center font-bold text-accent flex-shrink-0">{{ $bid['code'] }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[14px] font-bold text-accent">{{ $bid['name'] }}</p>
                        <div class="flex items-center gap-3 text-[11px] text-muted mt-0.5">
                            @if($bid['rating'] !== '—')
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3 h-3 text-[#F59E0B]" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                                {{ $bid['rating'] }}
                            </span>
                            @endif
                            @if($bid['compliance'] !== null)
                            <span>{{ __('rfq.compliance') }}: {{ $bid['compliance'] }}%</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="text-[18px] font-bold text-[#10B981]">{{ $bid['price'] }}</p>
                        <p class="text-[11px] text-muted">{{ __('rfq.delivery_in_days', ['days' => $bid['days']]) }}</p>
                    </div>
                    <a href="{{ route('dashboard.bids.show', ['id' => $bid['id']]) }}" class="w-9 h-9 rounded-lg bg-accent/10 flex items-center justify-center text-accent hover:bg-accent/20 transition-colors flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </a>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('rfq.no_bids_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Services Required --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('rfq.services_required') }}</h3>
            <div class="bg-page border border-th-border rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733"/></svg>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-primary">{{ ucfirst($rfq['target_role']) }}</p>
                    <p class="text-[11px] text-muted">{{ __('rfq.required') }}</p>
                </div>
            </div>
        </div>

        {{-- Budget --}}
        @if($rfq['budget'])
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('rfq.budget_info') }}</h3>
            <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('pr.estimated_budget') }}</p>
            <p class="text-[20px] font-bold text-[#10B981] mb-4">{{ $rfq['budget'] }}</p>
        </div>
        @endif

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('rfq.timeline') }}</h3>
            <div class="space-y-4">
                @php
                    $rfqEvents = array_filter([
                        ['done' => true, 'title' => __('rfq.timeline_published'), 'date' => $rfq['published']],
                        $rfq['deadline_raw'] ? ['done' => $rfq['deadline_raw']->isPast(), 'title' => __('rfq.bidding_deadline'), 'date' => $rfq['deadline']] : null,
                    ]);
                @endphp
                @foreach($rfqEvents as $event)
                <div class="flex items-start gap-3">
                    <div class="w-3 h-3 rounded-full {{ $event['done'] ? 'bg-[#10B981]' : 'bg-th-border' }} mt-1 flex-shrink-0"></div>
                    <div>
                        <p class="text-[13px] font-bold text-primary">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted">{{ $event['date'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- AI Insights --}}
        @if($rfq['avg_market_price'] || $rfq['typical_delivery'])
        <div class="bg-accent/5 border border-accent/20 rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-accent mb-4 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
                {{ __('rfq.ai_insights') }}
            </h3>
            <div class="space-y-4">
                @if($rfq['avg_market_price'])
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.avg_market_price') }}</p>
                    <p class="text-[14px] font-bold text-accent">{{ $rfq['budget_min'] }} - {{ $rfq['budget_max'] }}</p>
                </div>
                @endif
                @if($rfq['typical_delivery'])
                <div>
                    <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.typical_delivery') }}</p>
                    <p class="text-[14px] font-bold text-primary">{{ $rfq['typical_delivery'] }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
