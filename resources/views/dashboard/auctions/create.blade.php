@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('auction.enable_title'))

@section('content')

<div class="mb-6">
    <a href="{{ route('dashboard.rfqs.show', $rfq->id) }}"
       class="inline-flex items-center gap-2 text-[12px] text-muted hover:text-primary">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('auction.back_to_rfq') }}
    </a>
</div>

<x-dashboard.page-header :title="__('auction.enable_title')" :subtitle="$rfq->title" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('dashboard.auctions.enable', $rfq->id) }}" class="space-y-5">
    @csrf

    <div class="bg-surface border border-th-border rounded-2xl p-6 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.starts_at') }}</label>
                <input type="datetime-local" name="auction_starts_at" required
                       value="{{ old('auction_starts_at', now()->format('Y-m-d\TH:i')) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.ends_at') }}</label>
                <input type="datetime-local" name="auction_ends_at" required
                       value="{{ old('auction_ends_at', now()->addHour()->format('Y-m-d\TH:i')) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.reserve_optional') }} ({{ $rfq->currency }})</label>
                <input type="number" step="0.01" min="0" name="reserve_price" placeholder="—"
                       value="{{ old('reserve_price') }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                <p class="text-[10px] text-muted mt-1">{{ __('auction.reserve_hint') }}</p>
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.decrement_optional') }} ({{ $rfq->currency }})</label>
                <input type="number" step="0.01" min="0" name="bid_decrement" placeholder="—"
                       value="{{ old('bid_decrement') }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                <p class="text-[10px] text-muted mt-1">{{ __('auction.decrement_hint') }}</p>
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.anti_snipe') }}</label>
                <input type="number" min="0" max="3600" name="anti_snipe_seconds" required
                       value="{{ old('anti_snipe_seconds', 120) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                <p class="text-[10px] text-muted mt-1">{{ __('auction.anti_snipe_hint') }}</p>
            </div>
        </div>
    </div>

    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3">
        <a href="{{ route('dashboard.rfqs.show', $rfq->id) }}"
           class="h-11 px-5 rounded-xl bg-page border border-th-border text-[13px] font-semibold text-primary hover:bg-surface-2 transition-colors inline-flex items-center justify-center">
            {{ __('common.cancel') }}
        </a>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
            {{ __('auction.enable_button') }}
        </button>
    </div>
</form>

@endsection
