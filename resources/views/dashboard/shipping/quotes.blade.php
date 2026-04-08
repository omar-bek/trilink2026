@extends('layouts.dashboard', ['active' => 'shipments'])
@section('title', __('shipping.quotes_title'))

@section('content')

<x-dashboard.page-header :title="__('shipping.quotes_title')" :subtitle="__('shipping.quotes_subtitle')" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('dashboard.shipping.quotes.run') }}" class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.origin_city') }}</label>
            <input type="text" name="origin_city" required value="{{ old('origin_city', $request['origin']['city'] ?? '') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.origin_country') }}</label>
            <input type="text" name="origin_country" required maxlength="2" placeholder="AE" value="{{ old('origin_country', $request['origin']['country'] ?? '') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary uppercase focus:outline-none focus:border-accent" />
        </div>
        <div></div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.destination_city') }}</label>
            <input type="text" name="destination_city" required value="{{ old('destination_city', $request['destination']['city'] ?? '') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.destination_country') }}</label>
            <input type="text" name="destination_country" required maxlength="2" placeholder="SA" value="{{ old('destination_country', $request['destination']['country'] ?? '') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary uppercase focus:outline-none focus:border-accent" />
        </div>
        <div></div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.weight_kg') }}</label>
            <input type="number" step="0.1" min="0.1" name="weight_kg" required value="{{ old('weight_kg', $request['weight_kg'] ?? '5') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('shipping.parcels') }}</label>
            <input type="number" min="1" name="parcels" required value="{{ old('parcels', $request['parcels'] ?? '1') }}" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9-1.5h12.75m0 0V8.25c0-.414-.336-.75-.75-.75H6.75a.75.75 0 00-.75.75v9m12 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0M21 12.75H18M3 12.75h12.75"/></svg>
                {{ __('shipping.get_quotes') }}
            </button>
        </div>
    </div>
</form>

@if($rates !== null)
    @if(empty($rates))
        <div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1"/></svg>
            </div>
            <p class="text-[14px] font-bold text-primary">{{ __('shipping.no_rates') }}</p>
        </div>
    @else
    <div class="bg-surface border border-th-border rounded-2xl overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead class="bg-surface-2 border-b border-th-border">
                <tr class="text-[11px] text-muted uppercase tracking-wider">
                    <th class="text-start px-4 py-3">{{ __('shipping.carrier') }}</th>
                    <th class="text-start px-4 py-3">{{ __('shipping.service') }}</th>
                    <th class="text-end px-4 py-3">{{ __('shipping.transit') }}</th>
                    <th class="text-end px-4 py-3">{{ __('shipping.price') }}</th>
                </tr>
            </thead>
            <tbody class="text-[13px]">
                @foreach($rates as $i => $r)
                <tr class="border-b border-th-border hover:bg-surface-2/50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if($i === 0)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('shipping.best') }}</span>
                            @endif
                            <span class="font-semibold text-primary">{{ $r['carrier_name'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-muted capitalize">{{ $r['service'] }}</td>
                    <td class="px-4 py-3 text-end text-muted">{{ $r['transit_days'] }} {{ __('shipping.days') }}</td>
                    <td class="px-4 py-3 text-end font-bold {{ $i === 0 ? 'text-[#00d9b5]' : 'text-primary' }}">
                        {{ number_format($r['price'], 2) }} {{ $r['currency'] }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endif

@endsection
