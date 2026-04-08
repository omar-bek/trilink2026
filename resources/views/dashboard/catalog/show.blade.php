@extends('layouts.dashboard', ['active' => 'catalog'])
@section('title', $product->name)

@section('content')

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-6">
    <a href="{{ route('dashboard.catalog.browse') }}"
       class="inline-flex items-center gap-2 text-[12px] text-muted hover:text-primary">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('catalog.back_to_marketplace') }}
    </a>
</div>

@php
    $hasVariants = $product->relationLoaded('variants') && $product->variants->isNotEmpty();
    $productImages = collect($product->images ?? [])
        ->filter(fn ($p) => is_string($p) && $p !== '')
        ->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p))
        ->values()
        ->all();

    // Buy-panel state for the Alpine component. Extracted to a variable so
    // the @json directive below receives a single var instead of a multi-line
    // nested array literal — Blade's directive parser truncates the latter.
    $buyPanelState = [
        'base_price'    => (float) $product->base_price,
        'currency'      => $product->currency,
        'min_order_qty' => (int) $product->min_order_qty,
        'stock_qty'     => $product->stock_qty,
        'lead_time'     => (int) $product->lead_time_days,
        'unit'          => $product->unit,
        'variants'      => $hasVariants ? $product->variants->map(fn ($v) => [
            'id'             => $v->id,
            'name'           => $v->name,
            'price_modifier' => (float) $v->price_modifier,
            'stock_qty'      => $v->stock_qty,
            'is_active'      => $v->is_active,
        ])->all() : [],
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            @if(count($productImages) > 0)
            {{-- Gallery: large active image + thumbnail strip. The active
                 index is Alpine state so clicking a thumbnail swaps the
                 main view without a page reload. --}}
            <div x-data="{ active: 0, images: {{ \Illuminate\Support\Js::from($productImages) }} }" class="mb-5">
                <div class="aspect-video rounded-lg overflow-hidden bg-surface-2">
                    <img :src="images[active]" alt="{{ $product->name }}" class="w-full h-full object-cover">
                </div>
                @if(count($productImages) > 1)
                <div class="mt-3 grid grid-cols-5 gap-2">
                    @foreach($productImages as $i => $url)
                    <button type="button" @click="active = {{ $i }}"
                            :class="active === {{ $i }} ? 'border-accent ring-2 ring-accent/30' : 'border-th-border hover:border-accent/40'"
                            class="aspect-square rounded-lg overflow-hidden bg-surface-2 border transition-all">
                        <img src="{{ $url }}" alt="" loading="lazy" class="w-full h-full object-cover">
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            @else
            <div class="aspect-video rounded-lg bg-surface-2 mb-5 flex items-center justify-center text-muted">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/>
                </svg>
            </div>
            @endif
            <div class="flex items-center gap-2.5 mb-3 flex-wrap">
                @if($product->category)
                    <span class="text-[11px] text-[#8B5CF6] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 rounded-full px-2 py-0.5">{{ $product->category->name }}</span>
                @endif
                @if($product->sku)
                    <span class="text-[11px] font-mono text-muted">SKU: {{ $product->sku }}</span>
                @endif
                @if($product->hs_code)
                    <span class="text-[11px] font-mono text-muted">HS: {{ $product->hs_code }}</span>
                @endif
            </div>
            <h1 class="text-[24px] font-bold text-primary mb-2">{{ $product->name }}</h1>
            @if($product->name_ar)
                <p class="text-[14px] text-muted mb-4">{{ $product->name_ar }}</p>
            @endif
            <div class="text-[13px] text-muted leading-relaxed whitespace-pre-line">
                {{ $product->description ?? __('catalog.no_description') }}
            </div>
        </div>

        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('catalog.supplier') }}</h3>
            <div class="text-[14px] font-semibold text-primary">{{ $product->company?->name ?? '—' }}</div>
            <div class="text-[12px] text-muted mt-1">{{ $product->company?->country ?? '—' }}</div>
        </div>
    </div>

    {{-- Buy panel — variant-aware. When the product has variants the
         buyer picks one before adding to cart; the price + stock
         displayed below the dropdown live-updates. --}}
    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6"
             x-data="buyPanel({{ \Illuminate\Support\Js::from($buyPanelState) }})">
            <div class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.price') }}</div>
            <div class="text-[28px] font-bold text-[#00d9b5] mb-1">
                <span x-text="formattedPrice()"></span> {{ $product->currency }}
            </div>
            <div class="text-[12px] text-muted mb-5">{{ __('catalog.per') }} {{ $product->unit }}</div>

            @if($hasVariants)
            <div class="mb-5">
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.variant') }}</label>
                <select x-model.number="selectedVariantId"
                        class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                    <template x-for="v in variants" :key="v.id">
                        <option :value="v.id" x-text="v.name + (v.price_modifier ? ' (' + (v.price_modifier > 0 ? '+' : '') + v.price_modifier.toFixed(2) + ')' : '')"></option>
                    </template>
                </select>
            </div>
            @endif

            <div class="space-y-2 mb-5 text-[12px]">
                <div class="flex justify-between">
                    <span class="text-muted">{{ __('catalog.min_order_qty') }}</span>
                    <span class="text-primary font-semibold">{{ $product->min_order_qty }} {{ $product->unit }}</span>
                </div>
                <div class="flex justify-between" x-show="effectiveStock() !== null">
                    <span class="text-muted">{{ __('catalog.in_stock') }}</span>
                    <span class="text-primary font-semibold"><span x-text="effectiveStock()"></span> {{ $product->unit }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted">{{ __('catalog.lead_time') }}</span>
                    <span class="text-primary font-semibold">{{ $product->lead_time_days }} {{ __('catalog.days') }}</span>
                </div>
            </div>

            @auth
                @if(auth()->user()->company_id !== $product->company_id)
                <form method="POST" action="{{ route('dashboard.cart.add') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="product_variant_id" :value="selectedVariantId || ''">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.quantity') }}</label>
                        <input type="number" name="quantity" min="{{ $product->min_order_qty }}"
                               x-model.number="quantity"
                               required
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[14px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <button type="submit"
                            class="w-full h-12 rounded-xl bg-accent text-white text-[14px] font-bold hover:bg-accent/90 transition-colors">
                        + {{ __('catalog.add_to_cart') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('dashboard.catalog.buy', $product->id) }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="quantity" :value="quantity">
                    <button type="submit"
                            class="w-full h-11 rounded-xl bg-[#00d9b5] text-white text-[13px] font-bold hover:bg-[#00d9b5]/90 transition-colors"
                            onclick="return confirm('{{ __('catalog.confirm_buy_now') }}');">
                        {{ __('catalog.buy_now') }}
                    </button>
                </form>
                <p class="text-[10px] text-muted mt-3 leading-relaxed">{{ __('catalog.buy_now_explainer') }}</p>
                @else
                <div class="rounded-lg bg-surface-2 border border-th-border px-4 py-3 text-[12px] text-muted text-center">
                    {{ __('catalog.cannot_buy_own_product') }}
                </div>
                @endif
            @endauth
        </div>
    </div>
</div>

@push('scripts')
<script>
function buyPanel(initial) {
    return {
        basePrice:        initial.base_price,
        currency:         initial.currency,
        variants:         initial.variants || [],
        productStock:     initial.stock_qty,
        quantity:         initial.min_order_qty,
        selectedVariantId: (initial.variants && initial.variants[0]) ? initial.variants[0].id : null,
        currentVariant() {
            if (!this.selectedVariantId) return null;
            return this.variants.find(v => v.id === this.selectedVariantId) || null;
        },
        effectivePrice() {
            const v = this.currentVariant();
            return v ? (this.basePrice + v.price_modifier) : this.basePrice;
        },
        effectiveStock() {
            const v = this.currentVariant();
            if (v && v.stock_qty != null) return v.stock_qty;
            return this.productStock;
        },
        formattedPrice() {
            return this.effectivePrice().toFixed(2);
        },
    };
}
</script>
@endpush

@endsection
