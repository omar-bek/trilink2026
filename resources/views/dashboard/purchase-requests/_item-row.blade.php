{{-- Single line-item card — used both as the initial row in create.blade
     and as the <template> for addItem() JS cloning. --}}
@php $idx = $idx ?? 0; @endphp
<div data-item-card data-item-idx="{{ $idx }}" class="bg-page border border-th-border rounded-[12px] p-5">
    <div class="flex items-center justify-between mb-4">
        <span data-item-num class="text-[11px] text-muted font-mono font-semibold">{{ __('pr.item') }} #{{ $idx + 1 }}</span>
        <button type="button" onclick="removeItem(this)" class="inline-flex items-center gap-1 text-[11px] font-medium text-[#ff4d7f] hover:text-[#ff4d7f]/80 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ __('common.remove') ?? 'Remove' }}
        </button>
    </div>
    <div class="space-y-4">
        <div>
            <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.product_name') }} <span class="text-[#ff4d7f]">*</span></label>
            <input type="text" name="items[{{ $idx }}][name]" placeholder="{{ __('pr.product_name_placeholder') }}" required
                   class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.quantity') }} <span class="text-[#ff4d7f]">*</span></label>
                <input type="number" name="items[{{ $idx }}][qty]" min="1" placeholder="0" required
                       class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 tabular-nums transition-all">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.unit') }}</label>
                <select name="items[{{ $idx }}][unit]" class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                    <option value="pieces">{{ __('pr.unit_pieces') }}</option>
                    <option value="units">{{ __('pr.unit_units') }}</option>
                    <option value="boxes">{{ __('pr.unit_boxes') }}</option>
                    <option value="kg">{{ __('pr.unit_kg') }}</option>
                    <option value="tons">{{ __('pr.unit_tons') ?? 'Tons' }}</option>
                    <option value="meters">{{ __('pr.unit_meters') ?? 'Meters' }}</option>
                    <option value="liters">{{ __('pr.unit_liters') ?? 'Liters' }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.estimated_price') }}</label>
                <input type="number" name="items[{{ $idx }}][price]" min="0" step="0.01" placeholder="0.00"
                       class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 tabular-nums transition-all">
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.tech_specs') }}</label>
            <textarea name="items[{{ $idx }}][spec]" rows="2" placeholder="{{ __('pr.tech_specs_placeholder') }}"
                      class="w-full bg-surface border border-th-border rounded-[10px] px-4 py-2.5 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none transition-all"></textarea>
        </div>
    </div>
</div>

{{-- Template for JS cloning (rendered once, hidden) --}}
@once
<template id="item-template">
<div data-item-card class="bg-page border border-th-border rounded-[12px] p-5">
    <div class="flex items-center justify-between mb-4">
        <span data-item-num class="text-[11px] text-muted font-mono font-semibold"></span>
        <button type="button" onclick="removeItem(this)" class="inline-flex items-center gap-1 text-[11px] font-medium text-[#ff4d7f] hover:text-[#ff4d7f]/80 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ __('common.remove') ?? 'Remove' }}
        </button>
    </div>
    <div class="space-y-4">
        <div>
            <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.product_name') }} <span class="text-[#ff4d7f]">*</span></label>
            <input type="text" name="items[__IDX__][name]" placeholder="{{ __('pr.product_name_placeholder') }}" required
                   class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.quantity') }} <span class="text-[#ff4d7f]">*</span></label>
                <input type="number" name="items[__IDX__][qty]" min="1" placeholder="0" required
                       class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 tabular-nums transition-all">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.unit') }}</label>
                <select name="items[__IDX__][unit]" class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                    <option value="pieces">{{ __('pr.unit_pieces') }}</option>
                    <option value="units">{{ __('pr.unit_units') }}</option>
                    <option value="boxes">{{ __('pr.unit_boxes') }}</option>
                    <option value="kg">{{ __('pr.unit_kg') }}</option>
                    <option value="tons">{{ __('pr.unit_tons') ?? 'Tons' }}</option>
                    <option value="meters">{{ __('pr.unit_meters') ?? 'Meters' }}</option>
                    <option value="liters">{{ __('pr.unit_liters') ?? 'Liters' }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.estimated_price') }}</label>
                <input type="number" name="items[__IDX__][price]" min="0" step="0.01" placeholder="0.00"
                       class="w-full bg-surface border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 tabular-nums transition-all">
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.tech_specs') }}</label>
            <textarea name="items[__IDX__][spec]" rows="2" placeholder="{{ __('pr.tech_specs_placeholder') }}"
                      class="w-full bg-surface border border-th-border rounded-[10px] px-4 py-2.5 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none transition-all"></textarea>
        </div>
    </div>
</div>
</template>
@endonce
