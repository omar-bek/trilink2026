@props(['taxRate' => null, 'categories' => collect()])

@php
$inputCls = 'w-full bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors';
$labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-faint mb-2';
@endphp

<div class="bg-surface border border-th-border rounded-[16px] p-[25px] space-y-8 max-w-4xl">

    {{-- ─────────────────────── Section: Identity ─────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.tax_rates.section.identity') }}</h4>
                <p class="text-[11px] text-muted">{{ __('admin.tax_rates.section.identity_help') }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.name') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <input type="text" name="name" required value="{{ old('name', $taxRate?->name) }}" class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.code') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <input type="text" name="code" required maxlength="32" value="{{ old('code', $taxRate?->code) }}" class="{{ $inputCls }} font-mono" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.rate') }} (%) <span class="text-[#ff4d7f] normal-case">*</span></label>
                <div class="relative">
                    <input type="number" step="0.01" min="0" max="100" name="rate" required value="{{ old('rate', $taxRate?->rate) }}" class="{{ $inputCls }} pe-10" />
                    <span class="absolute end-4 top-1/2 -translate-y-1/2 text-[12px] text-faint pointer-events-none">%</span>
                </div>
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.country') }}</label>
                <input type="text" name="country" maxlength="2" placeholder="AE" value="{{ old('country', $taxRate?->country) }}" class="{{ $inputCls }} uppercase" />
            </div>
        </div>
    </div>

    <div class="border-t border-th-border"></div>

    {{-- ─────────────────────── Section: Scope ─────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.tax_rates.section.scope') }}</h4>
                <p class="text-[11px] text-muted">{{ __('admin.tax_rates.section.scope_help') }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.category') }}</label>
                <select name="category_id" class="{{ $inputCls }}">
                    <option value="">{{ __('admin.tax_rates.all_categories') }}</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('category_id', $taxRate?->category_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.effective_from') }}</label>
                <input type="date" name="effective_from" value="{{ old('effective_from', optional($taxRate?->effective_from)->format('Y-m-d')) }}" class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.tax_rates.effective_to') }}</label>
                <input type="date" name="effective_to" value="{{ old('effective_to', optional($taxRate?->effective_to)->format('Y-m-d')) }}" class="{{ $inputCls }}" />
            </div>
        </div>

        <div class="mt-5">
            <label class="{{ $labelCls }}">{{ __('admin.tax_rates.description') }}</label>
            <textarea name="description" rows="3" class="{{ str_replace('h-11', 'min-h-[80px] py-3', $inputCls) }} resize-none">{{ old('description', $taxRate?->description) }}</textarea>
        </div>
    </div>

    <div class="border-t border-th-border"></div>

    {{-- ─────────────────────── Section: Flags ─────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.tax_rates.section.flags') }}</h4>
                <p class="text-[11px] text-muted">{{ __('admin.tax_rates.section.flags_help') }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label class="flex items-start gap-3 bg-surface-2 border border-th-border rounded-[12px] p-[17px] cursor-pointer hover:border-accent/40 transition-colors">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $taxRate?->is_active ?? true)) class="mt-0.5 w-4 h-4 rounded border-th-border bg-surface text-accent focus:ring-accent">
                <div>
                    <p class="text-[13px] font-semibold text-primary">{{ __('admin.tax_rates.active') }}</p>
                    <p class="text-[11px] text-muted mt-0.5">{{ __('admin.tax_rates.active_help') }}</p>
                </div>
            </label>
            <label class="flex items-start gap-3 bg-surface-2 border border-th-border rounded-[12px] p-[17px] cursor-pointer hover:border-accent/40 transition-colors">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $taxRate?->is_default ?? false)) class="mt-0.5 w-4 h-4 rounded border-th-border bg-surface text-accent focus:ring-accent">
                <div>
                    <p class="text-[13px] font-semibold text-primary">{{ __('admin.tax_rates.default') }}</p>
                    <p class="text-[11px] text-muted mt-0.5">{{ __('admin.tax_rates.default_help') }}</p>
                </div>
            </label>
        </div>
    </div>
</div>
