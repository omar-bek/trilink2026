@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('bids.submit_bid_for', ['id' => $rfq['id']]))

@php
    // VAT treatment radio options. Labels are translation keys so the
    // form auto-localises in RTL/AR.
    $treatments = [
        'exclusive'      => __('bids.tax_treatment_exclusive'),
        'inclusive'      => __('bids.tax_treatment_inclusive'),
        'not_applicable' => __('bids.tax_treatment_not_applicable'),
    ];

    $exemptionReasons = [
        'export'             => __('bids.tax_exempt_export'),
        'designated_zone'    => __('bids.tax_exempt_designated_zone'),
        'below_threshold'    => __('bids.tax_exempt_below_threshold'),
        'exempt_service'     => __('bids.tax_exempt_exempt_service'),
        'reverse_charge'     => __('bids.tax_exempt_reverse_charge'),
    ];

    // Incoterms with a short hint about who pays freight/insurance/customs.
    $incotermHints = [
        'EXW' => __('bids.incoterm_exw_hint'),
        'FCA' => __('bids.incoterm_fca_hint'),
        'CPT' => __('bids.incoterm_cpt_hint'),
        'CIP' => __('bids.incoterm_cip_hint'),
        'DAP' => __('bids.incoterm_dap_hint'),
        'DPU' => __('bids.incoterm_dpu_hint'),
        'DDP' => __('bids.incoterm_ddp_hint'),
        'FAS' => __('bids.incoterm_fas_hint'),
        'FOB' => __('bids.incoterm_fob_hint'),
        'CFR' => __('bids.incoterm_cfr_hint'),
        'CIF' => __('bids.incoterm_cif_hint'),
    ];

    // Default payment-schedule rows. Extracted to a variable so the @json
    // directive in the Alpine component below receives a single variable
    // instead of a multi-line nested array literal — Blade's directive
    // argument parser truncates the latter when it contains __() calls.
    $defaultSchedule = old('payment_schedule', [
        ['milestone' => __('bids.milestone_advance'),    'percentage' => 30],
        ['milestone' => __('bids.milestone_production'), 'percentage' => 40],
        ['milestone' => __('bids.milestone_delivery'),   'percentage' => 30],
    ]);

    $taxRateLabel = rtrim(rtrim(number_format((float) $rfq['tax_rate'], 2), '0'), '.');
@endphp

@section('content')

{{-- =====================================================================
     Page header — back link, title, status pill.
     ===================================================================== --}}
<div class="mb-6">
    <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}"
       class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3 transition-colors">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('bids.back_to_rfq_details') }}
    </a>

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0 flex-1">
            <p class="text-[12px] font-mono text-muted mb-1.5">#{{ $rfq['id'] }}</p>
            <h1 class="text-[26px] sm:text-[32px] lg:text-[36px] font-bold text-primary leading-tight tracking-[-0.02em]">
                {{ __('bids.submit_your_bid') }}
            </h1>
            <p class="text-[14px] text-muted mt-2 truncate">{{ $rfq['title'] }}</p>
        </div>
        <span class="inline-flex items-center gap-2 h-10 px-4 rounded-full bg-[#ff4d7f]/10 border border-[#ff4d7f]/25 text-[#ff4d7f] text-[13px] font-semibold flex-shrink-0">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
            {{ __('bids.deadline_label') }}: {{ $rfq['deadline'] }}
        </span>
    </div>
</div>

{{-- =====================================================================
     Validation errors banner.
     ===================================================================== --}}
@if($errors->any())
<div class="bg-[#ff4d7f]/[0.08] border border-[#ff4d7f]/30 rounded-2xl p-5 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
        <div class="flex-1 min-w-0">
            <p class="text-[14px] font-semibold text-[#ff4d7f] mb-2">{{ __('bids.fix_following') }}</p>
            <ul class="text-[13px] text-[#ff4d7f]/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

{{-- =====================================================================
     RFQ context strip — buyer / qty / budget / deadline. Always visible
     above the form so the supplier knows what they're bidding on.
     ===================================================================== --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6 mb-6">
    <div class="flex items-center gap-2 mb-4">
        <span class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </span>
        <h2 class="text-[15px] font-bold text-primary">{{ __('bids.rfq_summary') }}</h2>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('bids.buyer') }}</p>
            <p class="text-[14px] font-semibold text-primary truncate">{{ $rfq['buyer'] }}</p>
        </div>
        <div>
            <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.quantity') }}</p>
            <p class="text-[14px] font-semibold text-primary truncate">{{ $rfq['quantity'] }}</p>
        </div>
        <div>
            <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.budget_range') }}</p>
            <p class="text-[14px] font-semibold text-[#00d9b5] truncate">{{ $rfq['budget'] }}</p>
        </div>
        <div>
            <p class="text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('rfq.deadline') }}</p>
            <p class="text-[14px] font-semibold text-primary truncate">{{ $rfq['deadline'] }}</p>
        </div>
    </div>
</div>

{{-- =====================================================================
     IMPORTANT: enctype="multipart/form-data" is required for the file
     input below — without it the attachments[] field is silently dropped
     by the browser before it ever reaches the controller.
     ===================================================================== --}}
<form method="POST" action="{{ route('dashboard.bids.store', ['rfq' => $rfq['numeric_id']]) }}"
      enctype="multipart/form-data"
      x-data="bidForm({
          taxRate: {{ (float) $rfq['tax_rate'] }},
          currency: '{{ $rfq['currency'] }}',
          lineItems: @js($rfq['line_items'] ?? []),
          oldPrice: Number('{{ old('price', 0) }}') || 0,
          oldTreatment: '{{ old('tax_treatment', 'exclusive') }}',
          oldDeliveryDays: Number('{{ old('delivery_time_days', 0) }}') || 0,
          oldValidityDays: Number('{{ old('validity_days', 30) }}') || 30,
      })">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ============================================================
             MAIN COLUMN — sectioned form (2/3 of grid on lg+)
             ============================================================ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- ====================================================
                 SECTION 1 — Pricing & VAT
                 ==================================================== --}}
            <section class="bg-surface border border-th-border rounded-2xl overflow-hidden">
                <header class="flex items-start gap-4 px-6 pt-6 pb-5 border-b border-th-border">
                    <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent text-[14px] font-bold">1</span>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-[17px] font-bold text-primary leading-tight">{{ __('bids.pricing_details') }}</h2>
                        <p class="text-[13px] text-muted mt-0.5">{{ __('bids.section_pricing_sub') }}</p>
                    </div>
                </header>

                <div class="p-6 space-y-6">

                    {{-- ====== Per-line item pricing (only when the RFQ has structured items) ====== --}}
                    @if(!empty($rfq['line_items']))
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                            <h3 class="text-[14px] font-semibold text-primary">{{ __('bids.line_item_pricing') }}</h3>
                        </div>
                        <p class="text-[12px] text-muted mb-3">{{ __('bids.line_item_pricing_hint') }}</p>

                        <div class="bg-page border border-th-border rounded-xl overflow-hidden">
                            <div class="hidden md:grid grid-cols-[minmax(0,1.6fr)_90px_60px_120px_120px] gap-3 px-4 py-3 border-b border-th-border bg-surface-2/40">
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.item') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('bids.qty') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('bids.unit') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('bids.unit_price') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('common.total') }}</p>
                            </div>

                            <template x-for="(row, i) in lineItems" :key="i">
                                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1.6fr)_90px_60px_120px_120px] gap-3 px-4 py-3 border-b border-th-border last:border-b-0 items-center">
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-medium text-primary truncate" x-text="row.name"></p>
                                        <p class="text-[11px] text-muted truncate" x-show="row.spec" x-text="row.spec"></p>
                                        <input type="hidden" :name="`items[${i}][name]`" :value="row.name">
                                        <input type="hidden" :name="`items[${i}][spec]`" :value="row.spec">
                                        <input type="hidden" :name="`items[${i}][unit]`" :value="row.unit">
                                    </div>
                                    <input type="number" :name="`items[${i}][qty]`" x-model.number="row.qty"
                                           step="0.01" min="0"
                                           class="bg-surface border border-th-border rounded-lg px-2 h-9 text-[13px] text-primary text-end focus:outline-none focus:border-accent/60 transition-colors">
                                    <p class="text-[12px] text-muted text-end truncate" x-text="row.unit"></p>
                                    <input type="number" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price"
                                           step="0.01" min="0"
                                           :placeholder="row.target_unit_price ? '~' + fmt(row.target_unit_price) : '0.00'"
                                           class="bg-surface border border-th-border rounded-lg px-2 h-9 text-[13px] text-primary text-end focus:outline-none focus:border-accent/60 transition-colors">
                                    <p class="text-[13px] text-[#00d9b5] font-semibold text-end" x-text="currency + ' ' + fmt((row.qty || 0) * (row.unit_price || 0))"></p>
                                </div>
                            </template>

                            <div class="grid grid-cols-[1fr_120px] gap-3 px-4 py-3 border-t border-th-border bg-surface-2/40">
                                <p class="text-[13px] font-semibold text-primary">{{ __('bids.line_items_subtotal') }}</p>
                                <p class="text-[14px] font-bold text-[#00d9b5] text-end" x-text="currency + ' ' + fmt(linePriceTotal)"></p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ====== Headline price ====== --}}
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">
                            {{ __('bids.total_bid_amount_label', ['currency' => $rfq['currency']]) }} <span class="text-[#ff4d7f]">*</span>
                        </label>
                        <input type="number" name="price" step="0.01" min="0" required
                               x-model.number="price"
                               :readonly="lineItems.length > 0"
                               :class="lineItems.length > 0 ? 'bg-surface-2/40 cursor-not-allowed' : 'bg-page'"
                               placeholder="{{ __('bids.price_placeholder') }}"
                               class="w-full border border-th-border rounded-xl px-4 h-12 text-[15px] font-medium text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
                        <p class="text-[12px] text-muted mt-1.5" x-show="lineItems.length === 0">{{ __('rfq.budget_range') }}: {{ $rfq['budget'] }}</p>
                        <p class="text-[12px] text-accent mt-1.5" x-show="lineItems.length > 0" x-cloak>{{ __('bids.price_locked_to_lines') }}</p>
                    </div>

                    {{-- ====== VAT TREATMENT ====== --}}
                    <div class="pt-5 border-t border-th-border">
                        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-semibold text-primary">{{ __('bids.vat_treatment') }} <span class="text-[#ff4d7f]">*</span></p>
                                <p class="text-[12px] text-muted mt-0.5">{{ __('bids.vat_treatment_hint', ['rate' => $taxRateLabel]) }}</p>
                            </div>
                            @if(!empty($supplier_trn))
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-accent/10 border border-accent/20 text-accent text-[11px] font-semibold flex-shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                TRN: {{ $supplier_trn }}
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#ffb020]/10 border border-[#ffb020]/20 text-[#ffb020] text-[11px] font-semibold flex-shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/></svg>
                                {{ __('bids.no_trn_warning') }}
                            </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            @foreach($treatments as $key => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="tax_treatment" value="{{ $key }}"
                                       x-model="treatment" class="sr-only peer"
                                       @checked(old('tax_treatment', 'exclusive') === $key)>
                                <div class="bg-page border border-th-border rounded-xl px-4 py-3.5 text-center transition-all peer-checked:border-accent peer-checked:bg-accent/10 peer-checked:shadow-[0_0_0_3px_rgba(79,124,255,0.12)] hover:border-accent/40">
                                    <p class="text-[13px] font-semibold text-primary">{{ $label }}</p>
                                </div>
                            </label>
                            @endforeach
                        </div>

                        {{-- Exemption reason — only when "not_applicable" --}}
                        <div x-show="treatment === 'not_applicable'" x-cloak x-collapse class="mt-3">
                            <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.tax_exemption_reason') }} <span class="text-[#ff4d7f]">*</span></label>
                            <select name="tax_exemption_reason"
                                    class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 transition-colors">
                                <option value="">— {{ __('common.select') }} —</option>
                                @foreach($exemptionReasons as $key => $label)
                                <option value="{{ $key }}" @selected(old('tax_exemption_reason') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ====== Payment terms (free text) ====== --}}
                    <div class="pt-5 border-t border-th-border">
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.payment_terms_summary') }}</label>
                        <input type="text" name="payment_terms" value="{{ old('payment_terms', __('bids.payment_terms_default')) }}"
                               class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
                    </div>

                    {{-- ====== Payment Schedule table ====== --}}
                    <div class="pt-5 border-t border-th-border">
                        <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-semibold text-primary">{{ __('bids.payment_schedule') }}</p>
                                <p class="text-[12px] text-muted mt-0.5">{{ __('bids.payment_schedule_form_hint') }}</p>
                            </div>
                            <button type="button" @click="addScheduleRow()"
                                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg text-[13px] font-semibold text-accent bg-accent/10 border border-accent/20 hover:bg-accent/15 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                {{ __('bids.add_row') }}
                            </button>
                        </div>

                        <div class="bg-page border border-th-border rounded-xl overflow-hidden">
                            <div class="grid grid-cols-[1fr_110px_140px_40px] gap-3 px-4 py-3 border-b border-th-border bg-surface-2/40">
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.milestone') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('bids.percentage') }}</p>
                                <p class="text-[11px] font-semibold text-muted uppercase tracking-wider text-end">{{ __('bids.amount') }}</p>
                                <span></span>
                            </div>

                            <template x-for="(row, i) in scheduleRows" :key="i">
                                <div class="grid grid-cols-[1fr_110px_140px_40px] gap-3 px-4 py-3 border-b border-th-border last:border-b-0 items-center">
                                    <input type="text" :name="`payment_schedule[${i}][milestone]`" x-model="row.milestone"
                                           placeholder="{{ __('bids.milestone_placeholder') }}"
                                           class="bg-surface border border-th-border rounded-lg px-3 h-10 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
                                    <div class="relative">
                                        <input type="number" :name="`payment_schedule[${i}][percentage]`" x-model.number="row.percentage"
                                               step="0.01" min="0" max="100"
                                               class="w-full bg-surface border border-th-border rounded-lg ps-3 pe-7 h-10 text-[13px] text-primary text-end focus:outline-none focus:border-accent/60 transition-colors">
                                        <span class="absolute end-3 top-1/2 -translate-y-1/2 text-[12px] text-muted pointer-events-none">%</span>
                                    </div>
                                    <p class="text-[13px] text-[#00d9b5] font-semibold text-end font-mono" x-text="currency + ' ' + fmt(((breakdown.total) * (Number(row.percentage) || 0)) / 100)"></p>
                                    <button type="button" @click="removeScheduleRow(i)"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#ff4d7f] hover:bg-[#ff4d7f]/10 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79"/></svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="scheduleRows.length === 0" class="px-4 py-6 text-center text-[13px] text-muted">
                                {{ __('bids.no_milestones_yet') }}
                            </div>

                            <div class="grid grid-cols-[1fr_110px_140px_40px] gap-3 px-4 py-3 border-t border-th-border bg-surface-2/40 items-center">
                                <p class="text-[13px] font-semibold text-primary">{{ __('common.total') }}</p>
                                <p class="text-[13px] font-bold text-end font-mono"
                                   :class="scheduleValid ? 'text-[#00d9b5]' : 'text-[#ff4d7f]'"
                                   x-text="schedulePctTotal.toFixed(2) + '%'"></p>
                                <p class="text-[13px] font-bold text-[#00d9b5] text-end font-mono" x-text="currency + ' ' + fmt(breakdown.total)"></p>
                                <span></span>
                            </div>
                        </div>

                        <p x-show="!scheduleValid && scheduleRows.length > 0" x-cloak class="text-[12px] text-[#ff4d7f] mt-2 font-medium">
                            {{ __('bids.percentages_must_total_100') }}
                        </p>
                    </div>

                    {{-- ====== Validity (days) ====== --}}
                    <div class="pt-5 border-t border-th-border">
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.validity_days_label') }} <span class="text-[#ff4d7f]">*</span></label>
                        <input type="number" name="validity_days" required value="{{ old('validity_days', 30) }}" min="1" max="180"
                               x-model.number="validityDays"
                               class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 transition-colors">
                    </div>
                </div>
            </section>

            {{-- ====================================================
                 SECTION 2 — Delivery & Trade Terms (merged)
                 ==================================================== --}}
            <section class="bg-surface border border-th-border rounded-2xl overflow-hidden">
                <header class="flex items-start gap-4 px-6 pt-6 pb-5 border-b border-th-border">
                    <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 flex items-center justify-center text-[#8b5cf6] text-[14px] font-bold">2</span>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-[17px] font-bold text-primary leading-tight">{{ __('bids.delivery_and_trade') }}</h2>
                        <p class="text-[13px] text-muted mt-0.5">{{ __('bids.section_terms_sub') }}</p>
                    </div>
                </header>

                <div class="p-6 space-y-5">
                    {{-- Incoterm --}}
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.incoterm') }} <span class="text-[#ff4d7f]">*</span></label>
                        <select name="incoterm" required x-model="incoterm"
                                class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 transition-colors">
                            <option value="">— {{ __('common.select') }} —</option>
                            @foreach($incoterms as $code)
                            <option value="{{ $code }}" @selected(old('incoterm') === $code)>{{ $code }} — {{ __('bids.incoterm_' . strtolower($code) . '_label') }}</option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-muted mt-1.5" x-text="incotermHints[incoterm] || '{{ __('bids.incoterm_pick_hint') }}'"></p>
                    </div>

                    {{-- Country of origin + HS code (side-by-side on sm+) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.country_of_origin') }} <span class="text-[#ff4d7f]">*</span></label>
                            <select name="country_of_origin" required
                                    class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 transition-colors">
                                <option value="">— {{ __('common.select') }} —</option>
                                @foreach($countries as $code => $name)
                                <option value="{{ $code }}" @selected(old('country_of_origin') === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[12px] text-muted mt-1.5">{{ __('bids.country_of_origin_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.hs_code') }}</label>
                            <input type="text" name="hs_code" value="{{ old('hs_code') }}"
                                   placeholder="{{ __('bids.hs_code_placeholder') }}" maxlength="16"
                                   class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors font-mono">
                            <p class="text-[12px] text-muted mt-1.5">{{ __('bids.hs_code_hint') }}</p>
                        </div>
                    </div>

                    {{-- Delivery time + warranty (side-by-side on sm+) --}}
                    <div class="pt-5 border-t border-th-border grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.delivery_time_days_label') }} <span class="text-[#ff4d7f]">*</span></label>
                            <input type="number" name="delivery_time_days" required value="{{ old('delivery_time_days') }}" min="1" max="365"
                                   x-model.number="deliveryDays"
                                   placeholder="{{ __('bids.delivery_time_placeholder') }}"
                                   class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.warranty_months_label') }} <span class="text-[#ff4d7f]">*</span></label>
                            <input type="number" name="warranty_months" required value="{{ old('warranty_months', 12) }}" min="0"
                                   class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60 transition-colors">
                        </div>
                    </div>
                </div>
            </section>

            {{-- ====================================================
                 SECTION 3 — Specifications & Notes
                 ==================================================== --}}
            <section class="bg-surface border border-th-border rounded-2xl overflow-hidden">
                <header class="flex items-start gap-4 px-6 pt-6 pb-5 border-b border-th-border">
                    <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center text-[#00d9b5] text-[14px] font-bold">3</span>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-[17px] font-bold text-primary leading-tight">{{ __('bids.tech_specs') }}</h2>
                        <p class="text-[13px] text-muted mt-0.5">{{ __('bids.section_specs_sub') }}</p>
                    </div>
                </header>

                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.tech_specs_compliance_label') }} <span class="text-[#ff4d7f]">*</span></label>
                        <textarea name="tech_specs" rows="5" required
                                  placeholder="{{ __('bids.tech_specs_placeholder') }}"
                                  class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors resize-none">{{ old('tech_specs') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-1.5">{{ __('bids.additional_notes') }}</label>
                        <textarea name="notes" rows="4"
                                  placeholder="{{ __('bids.additional_notes_placeholder') }}"
                                  class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors resize-none">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </section>

            {{-- ====================================================
                 SECTION 4 — Attachments (drag & drop)
                 ==================================================== --}}
            <section x-data="dropzone()"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="handleDrop($event, $refs.fileInput)"
                     class="bg-surface border border-th-border rounded-2xl overflow-hidden">
                <header class="flex items-start gap-4 px-6 pt-6 pb-5 border-b border-th-border">
                    <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center text-[#ffb020] text-[14px] font-bold">4</span>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-[17px] font-bold text-primary leading-tight">{{ __('bids.supporting_documents') }}</h2>
                        <p class="text-[13px] text-muted mt-0.5">{{ __('bids.section_attachments_sub') }}</p>
                    </div>
                </header>

                <div class="p-6">
                    <label :class="dragging ? 'border-accent bg-accent/5' : 'border-th-border bg-page'"
                           class="block border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer hover:border-accent/40">
                        <svg class="w-10 h-10 text-muted mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5v9"/></svg>
                        <p class="text-[14px] font-semibold text-primary mb-1">{{ __('bids.drag_drop_hint') }}</p>
                        <p class="text-[12px] text-muted">{{ __('bids.upload_constraints') }}</p>
                        <input type="file" name="attachments[]" multiple x-ref="fileInput"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                               @change="handleChange($event)"
                               class="hidden">
                    </label>

                    <div x-show="files.length > 0" x-cloak class="mt-4 space-y-2">
                        <template x-for="(f, i) in files" :key="i">
                            <div class="flex items-center gap-3 bg-page border border-th-border rounded-xl px-3 py-2.5">
                                <span class="w-8 h-8 rounded-lg bg-accent/10 flex items-center justify-center text-accent flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-primary truncate" x-text="f.name"></p>
                                    <p class="text-[11px] text-muted" x-text="formatBytes(f.size)"></p>
                                </div>
                                <button type="button" @click="removeFile(i, $refs.fileInput)"
                                        :aria-label="'{{ __('bids.remove_file') }}'"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:text-[#ff4d7f] hover:bg-[#ff4d7f]/10 transition-colors flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        {{-- ============================================================
             SIDEBAR — sticky bid summary + tips (1/3 of grid on lg+)
             ============================================================ --}}
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-6 space-y-6">

                {{-- ====== Sticky Bid Summary panel ====== --}}
                <div class="bg-surface border border-th-border rounded-2xl overflow-hidden shadow-[0_8px_24px_-12px_rgba(0,0,0,0.3)]">
                    <header class="px-6 pt-6 pb-4 border-b border-th-border">
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wider mb-1">{{ __('bids.bid_summary_panel') }}</p>
                        <p class="text-[28px] font-bold text-[#00d9b5] font-mono leading-none mt-2"
                           x-text="currency + ' ' + fmt(breakdown.total)"></p>
                    </header>

                    <div class="px-6 py-4 space-y-2.5 border-b border-th-border">
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-muted">{{ __('bids.subtotal') }}</span>
                            <span class="text-primary font-mono" x-text="currency + ' ' + fmt(breakdown.subtotal)"></span>
                        </div>
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-muted" x-text="treatment === 'not_applicable' ? '{{ __('bids.vat') }} (0%)' : '{{ __('bids.vat') }} (' + rate.toFixed(2) + '%)'"></span>
                            <span class="text-primary font-mono" x-text="currency + ' ' + fmt(breakdown.tax)"></span>
                        </div>
                    </div>

                    <div class="px-6 py-4 space-y-2.5 border-b border-th-border">
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-muted inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                                {{ __('bids.delivery_compact_label') }}
                            </span>
                            <span class="text-primary font-medium" x-text="(deliveryDays || '—') + ' {{ __('common.days') }}'"></span>
                        </div>
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-muted inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                {{ __('bids.validity_compact_label') }}
                            </span>
                            <span class="text-primary font-medium" x-text="(validityDays || '—') + ' {{ __('common.days') }}'"></span>
                        </div>
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-muted inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/><circle cx="12" cy="12" r="9"/></svg>
                                {{ __('bids.payment_schedule') }}
                            </span>
                            <span class="font-semibold inline-flex items-center gap-1"
                                  :class="scheduleValid ? 'text-[#00d9b5]' : 'text-[#ffb020]'">
                                <span x-text="scheduleValid ? '{{ __('bids.schedule_status_ok') }}' : '{{ __('bids.schedule_status_off') }}'"></span>
                                <svg x-show="scheduleValid" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <svg x-show="!scheduleValid" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="p-6 space-y-3">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl text-[14px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-all shadow-[0_8px_24px_-8px_rgba(0,217,181,0.55)] disabled:opacity-70 disabled:cursor-wait">
                            <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span>{{ __('rfq.submit_bid') }}</span>
                        </button>
                        <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}"
                           class="w-full inline-flex items-center justify-center h-11 px-6 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
                            {{ __('common.cancel') }}
                        </a>
                        <p class="text-[11px] text-muted text-center leading-relaxed pt-1">{{ __('bids.review_before_submit') }}</p>
                    </div>
                </div>

                {{-- ====== Tips / Important Notes ====== --}}
                <div class="bg-[#ffb020]/[0.05] border border-[#ffb020]/25 rounded-2xl p-5">
                    <div class="flex items-start gap-3 mb-3">
                        <svg class="w-5 h-5 text-[#ffb020] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/></svg>
                        <p class="text-[14px] font-bold text-[#ffb020]">{{ __('bids.important_notes') }}</p>
                    </div>
                    <ul class="text-[12.5px] text-muted space-y-2 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-[#ffb020] flex-shrink-0">•</span>
                            <span>{{ __('bids.note_binding') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#ffb020] flex-shrink-0">•</span>
                            <span>{{ __('bids.note_accurate') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#ffb020] flex-shrink-0">•</span>
                            <span>{{ __('bids.note_track') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-[#ffb020] flex-shrink-0">•</span>
                            <span>{{ __('bids.note_request_info') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>

    {{-- =====================================================================
         Mobile-only sticky bottom action bar — keeps the total + submit
         in view on small screens since the sticky sidebar doesn't apply.
         ===================================================================== --}}
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-surface border-t border-th-border px-4 py-3 shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.4)]">
        <div class="flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.bid_summary_panel') }}</p>
                <p class="text-[18px] font-bold text-[#00d9b5] font-mono truncate" x-text="currency + ' ' + fmt(breakdown.total)"></p>
            </div>
            <button type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-all disabled:opacity-70 disabled:cursor-wait">
                <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span>{{ __('rfq.submit_bid') }}</span>
            </button>
        </div>
    </div>

    {{-- Spacer so content above the mobile sticky bar isn't covered. --}}
    <div class="lg:hidden h-20" aria-hidden="true"></div>
</form>

@push('scripts')
<script>
function bidForm({ taxRate, currency, lineItems, oldPrice, oldTreatment, oldDeliveryDays, oldValidityDays }) {
    return {
        // ====== State ======
        rate: Number(taxRate) || 0,
        currency: currency || 'AED',
        lineItems: (lineItems || []).map(it => ({
            name: it.name || '',
            spec: it.spec || '',
            qty: Number(it.qty) || 0,
            unit: it.unit || '',
            unit_price: 0,
            target_unit_price: it.target_unit_price ? Number(it.target_unit_price) : null,
        })),
        price: oldPrice || 0,
        treatment: oldTreatment || 'exclusive',
        incoterm: '',
        deliveryDays: oldDeliveryDays || 0,
        validityDays: oldValidityDays || 30,
        submitting: false,

        scheduleRows: @json($defaultSchedule),

        // ====== Hint copy passed in from blade ======
        incotermHints: @json($incotermHints),

        // ====== Computed ======
        get linePriceTotal() {
            return this.lineItems.reduce((s, r) => s + (Number(r.qty) || 0) * (Number(r.unit_price) || 0), 0);
        },
        get effectivePrice() {
            // If line items exist with values, the headline price tracks them.
            return this.lineItems.length > 0 ? this.linePriceTotal : (Number(this.price) || 0);
        },
        get breakdown() {
            const p = this.effectivePrice;
            if (p <= 0) return { subtotal: 0, tax: 0, total: 0 };

            if (this.treatment === 'inclusive') {
                const total    = p;
                const subtotal = this.rate > 0 ? p / (1 + this.rate / 100) : p;
                const tax      = total - subtotal;
                return { subtotal, tax, total };
            }
            if (this.treatment === 'not_applicable') {
                return { subtotal: p, tax: 0, total: p };
            }
            // exclusive (default)
            const tax   = p * this.rate / 100;
            const total = p + tax;
            return { subtotal: p, tax, total };
        },
        get schedulePctTotal() {
            return this.scheduleRows.reduce((s, r) => s + (Number(r.percentage) || 0), 0);
        },
        get scheduleValid() {
            return Math.abs(this.schedulePctTotal - 100) < 0.01;
        },

        // ====== Methods ======
        fmt(v) {
            return (Number(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        addScheduleRow() { this.scheduleRows.push({ milestone: '', percentage: 0 }); },
        removeScheduleRow(i) { this.scheduleRows.splice(i, 1); },

        // ====== init ======
        init() {
            // Keep the headline price field in sync with line items.
            this.$watch('linePriceTotal', (v) => {
                if (this.lineItems.length > 0) this.price = Math.round(v * 100) / 100;
            });
            // Disable double-submit. We listen on the form element so the
            // spinner state covers both the sidebar and mobile-bar buttons.
            this.$root.addEventListener('submit', () => { this.submitting = true; });
        },
    };
}

// Lightweight dropzone component. Drag/drop drops files onto the hidden
// <input>; click bubbles up via the <label> wrapper, so the input is the
// single source of truth and the form submits multipart correctly.
function dropzone() {
    return {
        files: [],
        dragging: false,
        handleChange(e) {
            this.files = Array.from(e.target.files || []);
        },
        handleDrop(e, input) {
            this.dragging = false;
            const dropped = Array.from(e.dataTransfer?.files || []);
            if (dropped.length === 0) return;
            // DataTransfer is the only way to programmatically set <input type=file>'s files.
            const dt = new DataTransfer();
            dropped.forEach(f => dt.items.add(f));
            input.files = dt.files;
            this.files = dropped;
        },
        removeFile(i, input) {
            this.files.splice(i, 1);
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f));
            input.files = dt.files;
        },
        formatBytes(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0; let n = bytes;
            while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
            return n.toFixed(n >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
        },
    };
}
</script>
@endpush

@endsection
