@extends('layouts.dashboard', ['active' => 'shipments'])
@section('title', __('shipments.title'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        @if($shipment['contract_id'])
        <a href="{{ route('dashboard.contracts.show', ['id' => $shipment['contract_id']]) }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('shipments.back_to_contract') }}
        </a>
        @endif
        <p class="text-[12px] font-mono text-muted mb-1">{{ $shipment['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary">{{ __('shipments.title') }}</h1>
        <p class="text-[14px] text-muted mt-1">{{ $shipment['title'] }}</p>
    </div>
</div>

{{-- Real-time tracking map (Livewire + Reverb + Leaflet) --}}
<div class="mb-6">
    <livewire:live-tracking-map :shipment-id="(int) $shipment['numeric_id']" />
</div>

{{-- Status banner --}}
<div class="bg-accent/5 border border-accent/20 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-accent/15 flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25"/></svg>
    </div>
    <div class="flex-1">
        <h3 class="text-[16px] font-bold text-primary">{{ __('status.' . $shipment['status']) }}</h3>
        @if($shipment['eta'])
        <p class="text-[12px] text-muted">{{ __('shipments.estimated_arrival', ['date' => $shipment['eta']]) }}@if($shipment['days_remaining']) · {{ __('shipments.days_remaining', ['days' => $shipment['days_remaining']]) }}@endif</p>
        @endif
    </div>
    <div class="text-end">
        <p class="text-[11px] text-muted">{{ __('common.progress') }}</p>
        <p class="text-[20px] font-bold text-accent">{{ $shipment['progress'] }}%</p>
        <div class="w-32 h-1.5 bg-elevated rounded-full overflow-hidden mt-1"><div class="h-full bg-accent rounded-full" style="width: {{ $shipment['progress'] }}%"></div></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Origin / Destination --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6 grid grid-cols-2 gap-6">
            <div>
                <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('shipments.from') }}</p>
                <p class="text-[14px] font-bold text-primary">{{ $shipment['from'] }}</p>
            </div>
            <div>
                <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('shipments.to') }}</p>
                <p class="text-[14px] font-bold text-primary">{{ $shipment['to'] }}</p>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('shipments.tracking_timeline') }}</h3>
            <div class="space-y-5">
                @foreach($shipment['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)<div class="absolute start-3 top-7 w-0.5 h-full bg-th-border"></div>@endif
                    <div class="w-6 h-6 rounded-full {{ $event['done'] ? ($event['current'] ? 'bg-[#F59E0B]' : 'bg-[#10B981]') : 'bg-surface-2 border border-th-border' }} flex items-center justify-center flex-shrink-0 z-10">
                        @if($event['done'])<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        @else<svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>@endif
                    </div>
                    <div class="flex-1 min-w-0 pb-4">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-[14px] font-bold {{ $event['current'] ? 'text-[#F59E0B]' : 'text-primary' }}">{{ $event['title'] }}</p>
                            @if($event['current'])<span class="text-[10px] font-bold text-[#F59E0B] bg-[#F59E0B]/10 border border-[#F59E0B]/20 rounded-full px-2 py-0.5">{{ __('common.current') }}</span>@endif
                        </div>
                        @if($event['desc'])<p class="text-[12px] text-muted">{{ $event['desc'] }}</p>@endif
                        @if($event['time'] || $event['location'])
                        <div class="flex items-center gap-3 mt-1 text-[11px] text-faint">
                            @if($event['time'])<span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>{{ $event['time'] }}</span>@endif
                            @if($event['location'])<span class="inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>{{ $event['location'] }}</span>@endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recent GPS Updates --}}
        @if(!empty($shipment['gps_updates']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('shipments.recent_gps') }}</h3>
            <div class="space-y-2">
                @foreach($shipment['gps_updates'] as $u)
                <div class="bg-page border border-th-border rounded-xl p-3 flex items-center gap-3">
                    <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12z"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-primary">{{ $u['title'] }}</p>
                        @if($u['location'])<p class="text-[11px] text-muted truncate">{{ $u['location'] }}</p>@endif
                    </div>
                    <span class="text-[11px] text-faint flex-shrink-0">{{ $u['time'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Logistics Provider --}}
        @if($shipment['carrier'])
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('shipments.logistics_provider') }}</h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-lg bg-[#F59E0B] flex items-center justify-center font-bold text-white">{{ $shipment['carrier_code'] }}</div>
                <div>
                    <p class="text-[14px] font-bold text-primary">{{ $shipment['carrier'] }}</p>
                    @if($shipment['carrier_phone'])<p class="text-[11px] text-muted">{{ $shipment['carrier_phone'] }}</p>@endif
                </div>
            </div>
            @if($shipment['carrier_phone'])
            <a href="tel:{{ $shipment['carrier_phone'] }}" class="w-full mb-2 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372"/></svg>
                {{ __('shipments.call_carrier') }}
            </a>
            @endif
            @if($shipment['carrier_email'])
            <a href="mailto:{{ $shipment['carrier_email'] }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                {{ __('shipments.send_email') }}
            </a>
            @endif
        </div>
        @endif

        @if($shipment['notes'])
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('shipments.notes') }}</h3>
            <p class="text-[12px] text-muted leading-relaxed">{{ $shipment['notes'] }}</p>
        </div>
        @endif

        {{-- Quick Actions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.quick_actions') }}</h3>
            <div class="space-y-2">
                <a href="{{ route('dashboard.disputes') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                    {{ __('contracts.report_issue') }}
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
