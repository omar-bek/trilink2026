@extends('layouts.dashboard', ['active' => 'products'])
@section('title', __('catalog.my_products'))

@section('content')

<x-dashboard.page-header :title="__('catalog.my_products')" :subtitle="__('catalog.my_products_subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

<div class="mb-4 flex justify-end">
    <a href="{{ route('dashboard.products.create') }}"
       class="group inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        {{ __('catalog.add_product') }}
    </a>
</div>

<div class="bg-surface border border-th-border rounded-2xl overflow-x-auto">
    <table class="w-full min-w-[820px]">
        <thead class="bg-surface-2 border-b border-th-border">
            <tr class="text-[11px] text-muted uppercase tracking-wider">
                <th class="text-start px-4 py-3">{{ __('catalog.product') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.sku') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.category') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.price') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.stock') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.lead_time') }}</th>
                <th class="text-start px-4 py-3">{{ __('catalog.status') }}</th>
                <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody class="text-[13px]">
            @forelse($products as $p)
            @php
                $primary = collect($p->images ?? [])->first();
                $primaryUrl = $primary ? \Illuminate\Support\Facades\Storage::disk('public')->url($primary) : null;
            @endphp
            <tr class="border-b border-th-border hover:bg-surface-2/50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-surface-2 border border-th-border flex items-center justify-center">
                            @if($primaryUrl)
                                <img src="{{ $primaryUrl }}" alt="" class="w-full h-full object-cover">
                            @else
                                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-primary truncate">{{ $p->name }}</div>
                            @if($p->name_ar)
                                <div class="text-[11px] text-muted truncate">{{ $p->name_ar }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 font-mono text-muted text-[11px]">{{ $p->sku ?? '—' }}</td>
                <td class="px-4 py-3 text-muted">{{ $p->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-primary font-semibold">{{ number_format((float) $p->base_price, 2) }} {{ $p->currency }}</td>
                <td class="px-4 py-3 text-muted">{{ $p->stock_qty ?? '—' }} {{ $p->unit }}</td>
                <td class="px-4 py-3 text-muted">{{ $p->lead_time_days }} {{ __('catalog.days') }}</td>
                <td class="px-4 py-3">
                    @if($p->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('catalog.active') }}</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">{{ __('catalog.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-end">
                    <a href="{{ route('dashboard.products.edit', $p->id) }}" class="text-accent hover:underline text-[12px] font-semibold">{{ __('common.edit') }}</a>
                    <form method="POST" action="{{ route('dashboard.products.destroy', $p->id) }}" class="inline" onsubmit="return confirm('{{ __('catalog.confirm_delete') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold ms-3">{{ __('common.delete') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-12 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    </div>
                    <p class="text-[14px] font-bold text-primary">{{ __('catalog.no_products') }}</p>
                    <p class="text-[12px] text-muted mt-1">{{ __('catalog.no_products_hint') ?? __('common.try_different_filters') }}</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>

@endsection
