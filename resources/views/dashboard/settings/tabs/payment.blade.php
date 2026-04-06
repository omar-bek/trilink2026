<h3 class="text-[18px] font-bold text-primary mb-6">{{ __('settings.payment_methods') }}</h3>

{{-- Bank Transfer (placeholder — payment provider integration is per-tenant) --}}
<div class="bg-page border border-th-border rounded-xl p-5 flex items-center gap-4 mb-4">
    <div class="w-14 h-10 rounded-lg bg-accent/15 border border-accent/30 flex items-center justify-center">
        <span class="text-[11px] font-bold text-accent">BANK</span>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-[14px] font-bold text-primary">Bank Transfer</p>
        <p class="text-[12px] text-muted">Configure on contract checkout</p>
    </div>
    <span class="text-[11px] font-semibold text-[#10B981] bg-[#10B981]/10 border border-[#10B981]/20 rounded-full px-2.5 py-1">Default</span>
</div>

<button type="button" class="w-full border-2 border-dashed border-th-border rounded-xl p-5 text-[13px] font-semibold text-muted hover:text-primary hover:border-accent/40 transition-colors">
    + Add Payment Method
</button>

<p class="mt-6 text-[12px] text-muted">Stripe and PayPal are integrated platform-wide via the API. Per-company gateway credentials can be added when needed.</p>
