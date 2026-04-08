@extends('layouts.dashboard', ['active' => 'cart'])
@section('title', __('cart.title'))

@section('content')

<x-dashboard.page-header :title="__('cart.title')" :subtitle="__('cart.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">{{ session('status') }}</div>
@endif
@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

@if(empty($grouped))
<div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-14 text-center">
    <div class="w-20 h-20 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-5">
        <svg class="w-9 h-9 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
        </svg>
    </div>
    <p class="text-[16px] sm:text-[18px] font-bold text-primary">{{ __('cart.empty_title') }}</p>
    <p class="text-[13px] text-muted mt-1 mb-5 max-w-[360px] mx-auto">{{ __('cart.empty_subtitle') }}</p>
    <a href="{{ route('dashboard.catalog.browse') }}" class="group inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        {{ __('cart.browse_catalog') }}
    </a>
</div>
@else
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Per-supplier cards. The multi-supplier checkout fans these out
         into one Contract per supplier — the visual grouping mirrors the
         downstream contract count. --}}
    <div class="lg:col-span-2 space-y-5">
        @foreach($grouped as $group)
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-th-border">
                <div>
                    <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('cart.supplier') }}</p>
                    <p class="text-[14px] font-bold text-primary">{{ $group['supplier_name'] }}</p>
                </div>
                <p class="text-[13px] font-bold text-[#00d9b5]">{{ $group['currency'] }} {{ number_format($group['total'], 2) }}</p>
            </div>
            <div class="space-y-3">
                @foreach($group['items'] as $item)
                <div class="flex items-start gap-4 p-3 bg-page border border-th-border rounded-xl">
                    <div class="w-14 h-14 rounded-lg bg-surface-2 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('dashboard.catalog.show', $item->product_id) }}" class="text-[13px] font-bold text-primary hover:text-accent line-clamp-2">{{ $item->name_snapshot }}</a>
                        @if($item->attributes_snapshot)
                        <p class="text-[10px] text-muted mt-0.5">
                            @foreach($item->attributes_snapshot as $k => $v){{ $k }}: {{ $v }}@if(!$loop->last) · @endif @endforeach
                        </p>
                        @endif
                        <p class="text-[11px] text-muted mt-1">{{ $item->currency }} {{ number_format((float) $item->unit_price, 2) }} / {{ $item->product?->unit ?? 'pcs' }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <form method="POST" action="{{ route('dashboard.cart.update', $item->id) }}" class="inline-flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="quantity" min="0" value="{{ $item->quantity }}"
                                   class="w-16 bg-surface-2 border border-th-border rounded px-2 py-1 text-[12px] text-primary text-center"
                                   onchange="this.form.submit()" />
                        </form>
                        <p class="text-[12px] font-bold text-primary">{{ $item->currency }} {{ number_format($item->lineTotal(), 2) }}</p>
                        <form method="POST" action="{{ route('dashboard.cart.remove', $item->id) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[10px] text-[#ff4d7f] hover:underline">{{ __('cart.remove') }}</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Order summary + checkout. Shows the per-currency totals because
         multi-supplier carts can mix currencies. --}}
    <div class="space-y-4">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('cart.summary') }}</h3>
            <div class="space-y-2 mb-5">
                <div class="flex justify-between text-[12px]">
                    <span class="text-muted">{{ __('cart.suppliers') }}</span>
                    <span class="text-primary font-semibold">{{ count($grouped) }}</span>
                </div>
                <div class="flex justify-between text-[12px]">
                    <span class="text-muted">{{ __('cart.items') }}</span>
                    <span class="text-primary font-semibold">{{ $cart->totalQuantity() }}</span>
                </div>
            </div>
            <div class="border-t border-th-border pt-3 space-y-1.5">
                <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('cart.totals') }}</p>
                @foreach($totals as $cur => $sum)
                <div class="flex justify-between text-[14px]">
                    <span class="text-muted">{{ $cur }}</span>
                    <span class="font-bold text-[#00d9b5]">{{ number_format($sum, 2) }}</span>
                </div>
                @endforeach
            </div>
            <p class="text-[11px] text-muted mt-4 leading-relaxed">{{ __('cart.checkout_explainer', ['count' => count($grouped)]) }}</p>
            <form method="POST" action="{{ route('dashboard.cart.checkout') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-xl bg-accent text-white text-[14px] font-bold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]"
                        onclick="return confirm('{{ __('cart.confirm_checkout') }}');">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('cart.checkout') }}
                </button>
            </form>
            <form method="POST" action="{{ route('dashboard.cart.clear') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-9 rounded-xl bg-page border border-th-border text-muted hover:text-[#ff4d7f] hover:border-[#ff4d7f]/30 text-[12px] transition-colors"
                        onclick="return confirm('{{ __('cart.confirm_clear') }}');">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    {{ __('cart.clear') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
