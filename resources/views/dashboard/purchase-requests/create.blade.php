@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.create_title'))

@section('content')

{{-- Header --}}
<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('dashboard.purchase-requests') }}" class="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-primary transition-colors">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('pr.title') }}
    </a>
    <span class="text-muted">/</span>
    <span class="text-[12px] font-semibold text-primary">{{ __('pr.create_title') }}</span>
</div>

<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <h1 class="text-[24px] sm:text-[30px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('pr.create_title') }}</h1>
        <p class="text-[13px] text-muted mt-1">{{ __('pr.create_subtitle') }}</p>
    </div>
</div>

{{-- Stepper --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-8 mb-6">
    @php
    $steps = [
        ['n' => 1, 'key' => 'general',  'label' => __('pr.general_info'),  'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
        ['n' => 2, 'key' => 'items',    'label' => __('pr.line_items'),    'icon' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z'],
        ['n' => 3, 'key' => 'locations','label' => __('pr.locations'),     'icon' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
        ['n' => 4, 'key' => 'review',   'label' => __('pr.review'),        'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    @endphp
    <div class="flex items-start justify-between">
        @foreach($steps as $i => $step)
        <div class="flex flex-col items-center text-center flex-1 min-w-0">
            <div data-step-circle="{{ $step['n'] }}" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center transition-all flex-shrink-0 {{ $i === 0 ? 'bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]' : 'bg-elevated border border-th-border text-muted' }}">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}"/></svg>
            </div>
            <span data-step-label="{{ $step['n'] }}" class="mt-2 sm:mt-3 text-[11px] sm:text-[12px] font-semibold leading-tight {{ $i === 0 ? 'text-primary' : 'text-muted' }}">{{ $step['label'] }}</span>
        </div>
        @if(!$loop->last)
        <div class="flex-shrink-0 w-6 sm:flex-1 sm:w-auto h-0.5 bg-th-border mt-5 sm:mt-6 mx-1 sm:mx-2 relative">
            <div data-step-line="{{ $step['n'] }}" class="absolute inset-y-0 start-0 bg-accent rounded-full transition-all duration-500" style="width: 0;"></div>
        </div>
        @endif
        @endforeach
    </div>
</div>

@if ($errors->any())
<div class="mb-6 bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-[12px] p-4 text-[13px] text-[#ff4d7f]">
    <div class="flex items-start gap-2 mb-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <ul class="list-disc ms-4 space-y-1">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<form id="pr-form" action="{{ route('dashboard.purchase-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
    @csrf

    {{-- ============================================================
         STEP 1: General Information
         ============================================================ --}}
    <div data-step-panel="1" class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-8">
        <div class="flex items-center gap-2 mb-6">
            <div class="w-1 h-5 rounded-full bg-accent" aria-hidden="true"></div>
            <h2 class="text-[18px] font-bold text-primary">{{ __('pr.general_information') }}</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            {{-- Title --}}
            <div>
                <label for="pr-title" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.request_title') }} <span class="text-[#ff4d7f]">*</span></label>
                <input id="pr-title" type="text" name="title" value="{{ old('title') }}" placeholder="{{ __('pr.title_placeholder') }}"
                       class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all" required>
            </div>

            {{-- Category --}}
            <div>
                <label for="pr-category" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.category') }} <span class="text-[#ff4d7f]">*</span></label>
                <select id="pr-category" name="category_id" class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none" required>
                    <option value="">{{ __('pr.select_category') ?? '-- Select --' }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Description --}}
            <div class="md:col-span-2">
                <label for="pr-desc" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.description') }}</label>
                <textarea id="pr-desc" name="description" rows="3" placeholder="{{ __('pr.description_placeholder') }}"
                          class="w-full bg-page border border-th-border rounded-[10px] px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 resize-none transition-all">{{ old('description') }}</textarea>
            </div>

            {{-- Currency --}}
            <div>
                <label for="pr-currency" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.currency') }} <span class="text-[#ff4d7f]">*</span></label>
                <select id="pr-currency" name="currency" class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none" required>
                    @foreach($currencies as $cur)
                        <option value="{{ $cur }}" @selected(old('currency', 'AED') === $cur)>{{ $cur }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Budget --}}
            <div>
                <label for="pr-budget" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.estimated_budget') }}</label>
                <input id="pr-budget" type="number" name="budget" value="{{ old('budget') }}" step="0.01" min="0" placeholder="0.00"
                       class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all tabular-nums">
            </div>

            {{-- Required Date --}}
            <div>
                <label for="pr-date" class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.expected_delivery') }}</label>
                <input id="pr-date" type="date" name="required_date" value="{{ old('required_date') }}" min="{{ date('Y-m-d') }}"
                       class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
            </div>
        </div>

        @include('dashboard.purchase-requests._step-nav', ['step' => 1, 'total' => 4])
    </div>

    {{-- ============================================================
         STEP 2: Line Items
         ============================================================ --}}
    <div data-step-panel="2" class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-8 hidden">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="w-1 h-5 rounded-full bg-[#00d9b5]" aria-hidden="true"></div>
                <h2 class="text-[18px] font-bold text-primary">{{ __('pr.line_items') }}</h2>
                <span id="items-count" class="text-[11px] text-muted font-semibold ms-2 tabular-nums">(1)</span>
            </div>
            <button type="button" onclick="addItem()" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-[10px] text-[12px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('pr.add_item') }}
            </button>
        </div>

        <div id="items-container" class="space-y-4">
            @include('dashboard.purchase-requests._item-row', ['idx' => 0])
        </div>

        {{-- Running total --}}
        <div id="items-total-strip" class="mt-5 pt-4 border-t border-th-border flex items-center justify-between">
            <span class="text-[12px] uppercase tracking-wider text-faint font-semibold">{{ __('pr.estimated_total') ?? 'Estimated Total' }}</span>
            <span id="items-total" class="text-[20px] font-bold text-[#00d9b5] tabular-nums">—</span>
        </div>

        @include('dashboard.purchase-requests._step-nav', ['step' => 2, 'total' => 4])
    </div>

    {{-- ============================================================
         STEP 3: Delivery & Logistics
         ============================================================ --}}
    <div data-step-panel="3" class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-8 hidden">
        <div class="flex items-center gap-2 mb-6">
            <div class="w-1 h-5 rounded-full bg-[#8b5cf6]" aria-hidden="true"></div>
            <h2 class="text-[18px] font-bold text-primary">{{ __('pr.locations_logistics') }}</h2>
        </div>

        <div class="space-y-5">
            {{-- Address --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.delivery_address') ?? 'Delivery Address' }}</label>
                    <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="{{ __('pr.delivery_address') }}"
                           class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.delivery_city') ?? 'City' }}</label>
                    <input type="text" name="delivery_city" value="{{ old('delivery_city') }}" placeholder="{{ __('pr.delivery_city_placeholder') }}"
                           class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 focus:ring-2 focus:ring-accent/15 transition-all">
                </div>
            </div>

            {{-- Delivery terms --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('pr.delivery_terms') }}</label>
                    <select name="delivery_terms" class="w-full bg-page border border-th-border rounded-[10px] px-4 h-11 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                        <option value="">{{ __('pr.select_terms') ?? '-- Select --' }}</option>
                        <option value="EXW" @selected(old('delivery_terms') === 'EXW')>EXW — Ex Works</option>
                        <option value="FOB" @selected(old('delivery_terms') === 'FOB')>FOB — Free on Board</option>
                        <option value="CIF" @selected(old('delivery_terms') === 'CIF')>CIF — Cost, Insurance & Freight</option>
                        <option value="DDP" @selected(old('delivery_terms') === 'DDP')>DDP — Delivered Duty Paid</option>
                        <option value="DAP" @selected(old('delivery_terms') === 'DAP')>DAP — Delivered at Place</option>
                    </select>
                </div>
            </div>

            {{-- Additional services --}}
            <div>
                <p class="text-[12px] font-semibold text-primary mb-3">{{ __('pr.additional_services') }}</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-4 bg-page border border-th-border rounded-[12px] cursor-pointer hover:border-accent/40 transition-colors">
                        <input type="checkbox" name="needs_logistics" value="1" {{ old('needs_logistics') ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded border-th-border bg-page text-accent focus:ring-accent/30">
                        <div>
                            <p class="text-[13px] font-semibold text-primary">{{ __('pr.logistics_services') }}</p>
                            <p class="text-[11px] text-muted mt-0.5">{{ __('pr.want_logistics') }}</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-4 bg-page border border-th-border rounded-[12px] cursor-pointer hover:border-accent/40 transition-colors">
                        <input type="checkbox" name="needs_clearance" value="1" {{ old('needs_clearance') ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded border-th-border bg-page text-[#8b5cf6] focus:ring-[#8b5cf6]/30">
                        <div>
                            <p class="text-[13px] font-semibold text-primary">{{ __('pr.customs_clearance') }}</p>
                            <p class="text-[11px] text-muted mt-0.5">{{ __('pr.want_customs') }}</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        @include('dashboard.purchase-requests._step-nav', ['step' => 3, 'total' => 4])
    </div>

    {{-- ============================================================
         STEP 4: Review & Submit
         ============================================================ --}}
    <div data-step-panel="4" class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-8 hidden">
        <div class="flex items-center gap-2 mb-6">
            <div class="w-1 h-5 rounded-full bg-[#00d9b5]" aria-hidden="true"></div>
            <h2 class="text-[18px] font-bold text-primary">{{ __('pr.review_submit') }}</h2>
        </div>

        {{-- General info review --}}
        <div class="bg-accent/5 border border-accent/15 rounded-[12px] p-5 mb-4">
            <h3 class="text-[13px] font-bold text-accent mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                {{ __('pr.general_information') }}
                <button type="button" onclick="setStep(1)" class="ms-auto text-[11px] font-medium text-accent hover:underline">{{ __('common.edit') }}</button>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-[13px]">
                <div><span class="text-muted text-[11px]">{{ __('pr.request_title') }}</span><p id="review-title" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted text-[11px]">{{ __('pr.category') }}</span><p id="review-category" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted text-[11px]">{{ __('pr.estimated_budget') }}</span><p id="review-budget" class="font-semibold text-[#00d9b5] mt-0.5">—</p></div>
                <div><span class="text-muted text-[11px]">{{ __('pr.expected_delivery') }}</span><p id="review-delivery" class="font-semibold text-primary mt-0.5">—</p></div>
                <div class="sm:col-span-2"><span class="text-muted text-[11px]">{{ __('pr.description') }}</span><p id="review-desc" class="font-medium text-body mt-0.5 line-clamp-2">—</p></div>
            </div>
        </div>

        {{-- Items review --}}
        <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/15 rounded-[12px] p-5 mb-4">
            <h3 class="text-[13px] font-bold text-[#00d9b5] mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                {{ __('pr.line_items') }}
                <button type="button" onclick="setStep(2)" class="ms-auto text-[11px] font-medium text-[#00d9b5] hover:underline">{{ __('common.edit') }}</button>
            </h3>
            <div id="review-items" class="space-y-2 text-[13px]">
                <p class="text-muted">{{ __('common.no_data') }}</p>
            </div>
        </div>

        {{-- Delivery review --}}
        <div class="bg-[#8b5cf6]/5 border border-[#8b5cf6]/15 rounded-[12px] p-5 mb-4">
            <h3 class="text-[13px] font-bold text-[#8b5cf6] mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                {{ __('pr.locations_logistics') }}
                <button type="button" onclick="setStep(3)" class="ms-auto text-[11px] font-medium text-[#8b5cf6] hover:underline">{{ __('common.edit') }}</button>
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-[13px]">
                <div><span class="text-muted text-[11px]">{{ __('pr.delivery_location') }}</span><p id="review-location" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted text-[11px]">{{ __('pr.delivery_terms') }}</span><p id="review-terms" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted text-[11px]">{{ __('pr.additional_services') }}</span><p id="review-services" class="font-semibold text-primary mt-0.5">—</p></div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="mt-6 pt-5 border-t border-th-border flex items-center justify-between gap-3 flex-wrap">
            <button type="button" onclick="prevStep(3)" class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('common.previous') }}
            </button>
            <div class="flex items-center gap-3">
                <button type="button" onclick="saveDraft()" class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.save_draft') }}
                </button>
                <button type="submit" class="inline-flex items-center gap-2 h-11 px-6 rounded-[12px] text-[13px] font-bold text-white bg-[#00d9b5] hover:bg-[#00b894] shadow-[0_10px_30px_-12px_rgba(0,217,181,0.55)] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    {{ __('pr.submit_request') }}
                </button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
let currentStep = 1;
const TOTAL = 4;

function setStep(step) {
    document.querySelectorAll('[data-step-panel]').forEach(p => p.classList.toggle('hidden', +p.dataset.stepPanel !== step));
    for (let i = 1; i <= TOTAL; i++) {
        const c = document.querySelector(`[data-step-circle="${i}"]`);
        const l = document.querySelector(`[data-step-label="${i}"]`);
        if (!c) continue;
        c.className = 'w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center transition-all flex-shrink-0';
        if (i < step) {
            c.className += ' bg-[#00d9b5] text-white';
            c.innerHTML = '<svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            l.className = 'mt-2 sm:mt-3 text-[11px] sm:text-[12px] font-semibold text-primary leading-tight';
        } else if (i === step) {
            c.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]';
            l.className = 'mt-2 sm:mt-3 text-[11px] sm:text-[12px] font-semibold text-primary leading-tight';
        } else {
            c.className += ' bg-elevated border border-th-border text-muted';
            l.className = 'mt-2 sm:mt-3 text-[11px] sm:text-[12px] font-semibold text-muted leading-tight';
        }
    }
    for (let i = 1; i < TOTAL; i++) {
        const line = document.querySelector(`[data-step-line="${i}"]`);
        if (!line) continue;
        line.style.width = (i < step) ? '100%' : '0';
    }
    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    const panel = document.querySelector(`[data-step-panel="${step}"]`);
    if (!panel) return true;
    const required = panel.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
        if (!el.value.trim()) {
            el.classList.add('border-[#ff4d7f]/60');
            el.addEventListener('input', () => el.classList.remove('border-[#ff4d7f]/60'), { once: true });
            valid = false;
        }
    });
    if (!valid) {
        const first = panel.querySelector('.border-\\[\\#ff4d7f\\]\\/60');
        first?.focus();
    }
    return valid;
}

function nextStep(n) {
    if (!validateStep(currentStep)) return;
    if (n === 4) populateReview();
    setStep(n);
}
function prevStep(n) { setStep(n); }

function populateReview() {
    const form = document.getElementById('pr-form');
    const fd = new FormData(form);

    document.getElementById('review-title').textContent = fd.get('title') || '—';
    document.getElementById('review-desc').textContent = fd.get('description') || '—';

    const catSelect = form.querySelector('select[name="category_id"]');
    document.getElementById('review-category').textContent =
        (catSelect?.selectedIndex > 0) ? catSelect.options[catSelect.selectedIndex].text : '—';

    const currency = fd.get('currency') || 'AED';
    const budget = fd.get('budget');
    document.getElementById('review-budget').textContent = budget ? `${currency} ${Number(budget).toLocaleString()}` : '—';

    document.getElementById('review-delivery').textContent = fd.get('required_date') || '—';

    const addr = fd.get('delivery_address') || '';
    const city = fd.get('delivery_city') || '';
    document.getElementById('review-location').textContent = [addr, city].filter(Boolean).join(', ') || '—';

    const termsSelect = form.querySelector('select[name="delivery_terms"]');
    document.getElementById('review-terms').textContent =
        (termsSelect?.selectedIndex > 0) ? termsSelect.options[termsSelect.selectedIndex].text : '—';

    const services = [];
    if (fd.get('needs_logistics')) services.push('{{ __("pr.logistics_services") }}');
    if (fd.get('needs_clearance')) services.push('{{ __("pr.customs_clearance") }}');
    document.getElementById('review-services').textContent = services.length ? services.join(', ') : '{{ __("pr.not_required") }}';

    // Items
    const items = [];
    let i = 0;
    while (true) {
        const name = fd.get(`items[${i}][name]`);
        if (name === null) break;
        if (name) {
            const qty = fd.get(`items[${i}][qty]`) || '0';
            const unit = fd.get(`items[${i}][unit]`) || '';
            const price = fd.get(`items[${i}][price]`);
            items.push({ name, qty, unit, price: price ? `${currency} ${Number(price).toLocaleString()}` : '—' });
        }
        i++;
    }
    const container = document.getElementById('review-items');
    if (items.length === 0) {
        container.innerHTML = `<p class="text-muted">{{ __('common.no_data') }}</p>`;
    } else {
        container.innerHTML = items.map((it, idx) =>
            `<div class="flex items-center justify-between py-2 ${idx > 0 ? 'border-t border-th-border' : ''}">
                <div>
                    <p class="font-semibold text-primary">${idx + 1}. ${esc(it.name)}</p>
                    <p class="text-[11px] text-muted">${esc(it.qty)} ${esc(it.unit)}</p>
                </div>
                <span class="font-bold text-[#00d9b5] tabular-nums">${esc(it.price)}</span>
            </div>`
        ).join('');
    }
}

function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
}

// --- Item management ---

function addItem() {
    const container = document.getElementById('items-container');
    const idx = container.children.length;
    if (idx >= 50) return; // max items
    const tpl = document.getElementById('item-template').content.cloneNode(true);
    tpl.querySelector('[data-item-card]').dataset.itemIdx = idx;
    tpl.querySelector('[data-item-num]').textContent = `{{ __("pr.item") }} #${idx + 1}`;
    tpl.querySelectorAll('[name]').forEach(el => el.name = el.name.replace('__IDX__', idx));
    container.appendChild(tpl);
    updateItemsCount();
}

function removeItem(btn) {
    const card = btn.closest('[data-item-card]');
    const container = document.getElementById('items-container');
    if (container.children.length <= 1) return; // keep at least 1
    card.remove();
    // Re-index remaining items
    container.querySelectorAll('[data-item-card]').forEach((card, i) => {
        card.dataset.itemIdx = i;
        card.querySelector('[data-item-num]').textContent = `{{ __("pr.item") }} #${i + 1}`;
        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
        });
    });
    updateItemsCount();
}

function updateItemsCount() {
    const count = document.getElementById('items-container').children.length;
    document.getElementById('items-count').textContent = `(${count})`;
    updateTotal();
}

function updateTotal() {
    const form = document.getElementById('pr-form');
    const currency = form.querySelector('select[name="currency"]')?.value || 'AED';
    let total = 0;
    let hasPrice = false;
    document.querySelectorAll('[data-item-card]').forEach(card => {
        const qty = parseFloat(card.querySelector('[name$="[qty]"]')?.value) || 0;
        const price = parseFloat(card.querySelector('[name$="[price]"]')?.value) || 0;
        if (price > 0) { total += qty * price; hasPrice = true; }
    });
    document.getElementById('items-total').textContent = hasPrice ? `${currency} ${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : '—';
}

function saveDraft() {
    const form = document.getElementById('pr-form');
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'draft'; input.value = '1';
    form.appendChild(input);
    form.submit();
}

document.getElementById('items-container').addEventListener('input', function(e) {
    if (e.target.name && (e.target.name.endsWith('[qty]') || e.target.name.endsWith('[price]'))) {
        updateTotal();
    }
});
</script>
@endpush

@endsection
