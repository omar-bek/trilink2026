@extends('layouts.app')

@section('title', __('register.page_title') . ' — TriLink Trading')

@section('content')

<x-landing.navbar />

<main class="relative bg-page min-h-screen pt-24 sm:pt-28 lg:pt-32 pb-12 sm:pb-20 overflow-hidden">
    <div class="pointer-events-none absolute inset-0 bg-spotlight opacity-50" aria-hidden="true"></div>

    <div class="relative mx-auto w-full max-w-[920px] px-4 sm:px-6 lg:px-10">

        {{-- Header --}}
        <header class="mb-8 sm:mb-10">
            <span class="t-eyebrow">{{ __('login.brand_eyebrow') }}</span>
            <h1 class="mt-3 font-display text-[28px] sm:text-[36px] lg:text-[40px] font-bold text-primary leading-tight">{{ __('register.page_title') }}</h1>
            <p class="mt-2 text-[14px] sm:text-[15px] text-muted max-w-[640px]">{{ __('register.subtitle') }}</p>
        </header>

        {{-- Stepper — horizontal on sm+, compact pill on mobile --}}
        <div class="mb-8 sm:mb-10">
            <div class="flex items-start justify-between relative">
                {{-- Connecting lines (hidden on mobile to avoid clipping) --}}
                <div class="absolute top-[18px] sm:top-[20px] start-[28px] sm:start-[40px] end-[28px] sm:end-[40px] h-[2px] bg-th-border z-0"></div>
                <div id="stepper-progress" class="absolute top-[18px] sm:top-[20px] start-[28px] sm:start-[40px] h-[2px] bg-accent z-0 transition-all duration-500" style="width: 0;"></div>

                @foreach([
                    1 => __('register.step_company_info'),
                    2 => __('register.step_legal_docs'),
                    3 => __('register.step_manager'),
                ] as $num => $label)
                <div class="flex flex-col items-center relative z-10 flex-1">
                    <div data-step-circle="{{ $num }}" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-semibold text-[13px] sm:text-[14px] transition-all duration-300 {{ $num === 1 ? 'bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]' : 'bg-surface-2 text-muted border border-th-border' }}">
                        <span data-step-num="{{ $num }}">{{ $num }}</span>
                        <svg data-step-check="{{ $num }}" class="w-4 h-4 sm:w-5 sm:h-5 hidden" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span data-step-label="{{ $num }}" class="mt-2 text-[11px] sm:text-[12px] font-medium text-center px-1 leading-snug {{ $num === 1 ? 'text-primary' : 'text-muted' }}">{{ $label }}</span>
                </div>
                @endforeach
            </div>
        </div>

        @if ($errors->any())
        <div role="alert" class="mb-6 bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] sm:text-[14px] font-bold text-[#FCA5A5] mb-1">{{ __('register.errors_title') }}</p>
                    <ul class="list-disc ms-5 space-y-0.5 text-[12px] text-[#FCA5A5]/90">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif

        {{-- novalidate is essential for a multi-step form: without it the
             browser tries to validate `required` fields in hidden panels
             when the user clicks Submit on the final step, fails because
             it cannot focus a `display:none` field to show its error, and
             silently aborts the submission. We do per-step validation in
             JS (goToStep) and full validation on the server instead. --}}
        <form id="registration-form" action="{{ route('register.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf

            {{-- ============================================
                 STEP 1: COMPANY INFORMATION
                 ============================================ --}}
            <div data-step-panel="1" class="bg-surface border border-th-border rounded-2xl sm:rounded-3xl p-6 sm:p-8 lg:p-10 shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)]">
                {{-- Section header --}}
                <div class="flex items-center gap-4 mb-7 sm:mb-8">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-[18px] sm:text-[20px] font-bold text-primary leading-tight">{{ __('register.section_company_title') }}</h2>
                        <p class="text-[12.5px] sm:text-[13px] text-muted mt-0.5">{{ __('register.section_company_subtitle') }}</p>
                    </div>
                </div>

                {{-- Form fields --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-auth.select :label="__('register.field_company_type')" name="company_type" required :placeholder="__('register.field_company_type_placeholder')" :options="$companyTypes" />
                    </div>

                    <x-auth.input :label="__('register.field_company_name_en')" name="company_name_en" :placeholder="__('register.field_company_name_en_placeholder')" required />
                    <x-auth.input :label="__('register.field_company_name_ar')" name="company_name_ar" :placeholder="__('register.field_company_name_ar_placeholder')" dir="rtl" />
                    <x-auth.input :label="__('register.field_trade_license')" name="trade_license" :placeholder="__('register.field_trade_license_placeholder')" required />
                    <x-auth.input :label="__('register.field_tax_number')" name="tax_number" :placeholder="__('register.field_tax_number_placeholder')" />
                    <x-auth.select :label="__('register.field_country')" name="country" required :placeholder="__('register.field_country_placeholder')" :options="$countries" />
                    <x-auth.input :label="__('register.field_city')" name="city" :placeholder="__('register.field_city_placeholder')" required />

                    <div class="md:col-span-2">
                        <x-auth.textarea :label="__('register.field_address')" name="address" :placeholder="__('register.field_address_placeholder')" required :rows="3" />
                    </div>

                    <x-auth.input :label="__('register.field_phone')" name="phone" type="tel" :placeholder="__('register.field_phone_placeholder')" required />
                    <x-auth.input :label="__('register.field_email')" name="email" type="email" :placeholder="__('register.field_email_placeholder')" required />

                    <div class="md:col-span-2">
                        <x-auth.input :label="__('register.field_website')" name="website" type="url" :placeholder="__('register.field_website_placeholder')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-auth.textarea :label="__('register.field_description')" name="description" :placeholder="__('register.field_description_placeholder')" :rows="4" />
                    </div>
                </div>

                {{-- Step nav --}}
                <div class="mt-8 sm:mt-10 pt-6 border-t border-th-border flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <button type="button" disabled class="px-6 py-2.5 rounded-xl text-[14px] font-medium text-muted bg-surface-2 border border-th-border opacity-50 cursor-not-allowed">{{ __('register.previous') }}</button>
                    <button type="button" onclick="goToStep(2)" class="group inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        {{ __('register.next_step') }}
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- ============================================
                 STEP 2: LEGAL DOCUMENTS
                 ============================================ --}}
            <div data-step-panel="2" class="bg-surface border border-th-border rounded-2xl sm:rounded-3xl p-6 sm:p-8 lg:p-10 shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)] hidden">
                <div class="flex items-center gap-4 mb-7 sm:mb-8">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-[18px] sm:text-[20px] font-bold text-primary leading-tight">{{ __('register.section_legal_title') }}</h2>
                        <p class="text-[12.5px] sm:text-[13px] text-muted mt-0.5">{{ __('register.section_legal_subtitle') }}</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <x-auth.upload :label="__('register.upload_trade_license')" :description="__('register.upload_trade_license_desc')" name="trade_license_file" required />
                    <x-auth.upload :label="__('register.upload_tax_certificate')" :description="__('register.upload_tax_certificate_desc')" name="tax_certificate_file" required />
                    <x-auth.upload :label="__('register.upload_company_profile')" :description="__('register.upload_company_profile_desc')" name="company_profile_file" />
                </div>

                <div class="mt-8 sm:mt-10 pt-6 border-t border-th-border flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <button type="button" onclick="goToStep(1)" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[14px] font-semibold text-primary bg-surface-2 border border-th-border hover:bg-elevated transition-colors">
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        {{ __('register.previous') }}
                    </button>
                    <button type="button" onclick="goToStep(3)" class="group inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        {{ __('register.next_step') }}
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- ============================================
                 STEP 3: MANAGER DETAILS
                 ============================================ --}}
            <div data-step-panel="3" class="bg-surface border border-th-border rounded-2xl sm:rounded-3xl p-6 sm:p-8 lg:p-10 shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)] hidden">
                <div class="flex items-center gap-4 mb-7 sm:mb-8">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-[18px] sm:text-[20px] font-bold text-primary leading-tight">{{ __('register.section_manager_title') }}</h2>
                        <p class="text-[12.5px] sm:text-[13px] text-muted mt-0.5">{{ __('register.section_manager_subtitle') }}</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <x-auth.input :label="__('register.field_manager_name')" name="manager_name" :placeholder="__('register.field_manager_name_placeholder')" required />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-auth.input :label="__('register.field_manager_email')" name="manager_email" type="email" :placeholder="__('register.field_manager_email_placeholder')" required />
                        <x-auth.input :label="__('register.field_manager_phone')" name="manager_phone" type="tel" :placeholder="__('register.field_manager_phone_placeholder')" required />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-auth.input :label="__('register.field_manager_password')" name="manager_password" type="password" :placeholder="__('register.field_manager_password_placeholder')" required />
                        <x-auth.input :label="__('register.field_manager_password_confirm')" name="manager_password_confirmation" type="password" :placeholder="__('register.field_manager_password_confirm_placeholder')" required />
                    </div>
                </div>

                {{-- Registration Summary --}}
                <div class="mt-8 bg-surface-2 border border-th-border rounded-xl p-5 sm:p-6">
                    <h3 class="text-[15px] sm:text-[16px] font-bold text-primary mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        {{ __('register.summary_title') }}
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-muted">{{ __('register.summary_company') }}</dt>
                            <dd id="summary-company" class="text-[12.5px] font-semibold text-primary truncate">—</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-muted">{{ __('register.summary_location') }}</dt>
                            <dd id="summary-location" class="text-[12.5px] font-semibold text-primary truncate">—</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-muted">{{ __('register.summary_docs') }}</dt>
                            <dd id="summary-docs" class="text-[12.5px] font-semibold text-primary">0 / 3</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[12.5px] text-muted">{{ __('register.summary_manager') }}</dt>
                            <dd id="summary-manager" class="text-[12.5px] font-semibold text-primary truncate">—</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-8 sm:mt-10 pt-6 border-t border-th-border flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <button type="button" onclick="goToStep(2)" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[14px] font-semibold text-primary bg-surface-2 border border-th-border hover:bg-elevated transition-colors">
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        {{ __('register.previous') }}
                    </button>
                    <button type="submit" class="group inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('register.submit') }}
                    </button>
                </div>
            </div>
        </form>

        {{-- What happens next --}}
        <div class="mt-6 bg-[#22C55E]/5 border border-[#22C55E]/20 rounded-xl p-5 flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-[#22C55E]/15 border border-[#22C55E]/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 text-[#22C55E]" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h4 class="text-[14px] font-bold text-primary mb-1">{{ __('register.what_next_title') }}</h4>
                <p class="text-[13px] text-muted leading-relaxed">{{ __('register.what_next_body') }}</p>
            </div>
        </div>

        {{-- Have an account link --}}
        <p class="mt-6 text-center text-[13px] text-muted">
            {{ __('register.have_account') }}
            <a href="{{ route('login') }}" class="font-semibold text-accent hover:text-accent-h transition-colors">{{ __('register.sign_in') }}</a>
        </p>
    </div>
</main>

<x-landing.footer />

@push('scripts')
<script>
const TOTAL_STEPS = 3;

// On a server-side validation failure we need to land the user on the
// step that actually contains the broken field — otherwise they bounce
// back to step 1 and have no idea what's wrong. Each field belongs to a
// known step; PHP figures out the lowest step with an error and hands it
// to JS as the initial currentStep.
@php
    $stepFields = [
        1 => ['company_type', 'company_name_en', 'company_name_ar', 'trade_license', 'tax_number', 'country', 'city', 'address', 'phone', 'email', 'website', 'description'],
        2 => ['trade_license_file', 'tax_certificate_file', 'company_profile_file'],
        3 => ['manager_name', 'manager_email', 'manager_phone', 'manager_password'],
    ];
    $initialStep = 1;
    if ($errors->any()) {
        foreach ($stepFields as $stepNum => $fields) {
            foreach ($fields as $f) {
                if ($errors->has($f)) {
                    $initialStep = $stepNum;
                    break 2;
                }
            }
        }
    }
@endphp
let currentStep = {{ $initialStep }};

function goToStep(step) {
    if (step < 1 || step > TOTAL_STEPS) return;

    // Validate current step before moving forward
    if (step > currentStep) {
        const currentPanel = document.querySelector(`[data-step-panel="${currentStep}"]`);
        const requiredFields = currentPanel.querySelectorAll('[required]');
        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return;
            }
        }
    }

    // If moving to summary step, populate it
    if (step === 3) populateSummary();

    currentStep = step;

    // Toggle panels
    document.querySelectorAll('[data-step-panel]').forEach(p => {
        p.classList.toggle('hidden', p.dataset.stepPanel != step);
    });

    // Update circles
    for (let i = 1; i <= TOTAL_STEPS; i++) {
        const circle = document.querySelector(`[data-step-circle="${i}"]`);
        const num = document.querySelector(`[data-step-num="${i}"]`);
        const check = document.querySelector(`[data-step-check="${i}"]`);
        const label = document.querySelector(`[data-step-label="${i}"]`);

        circle.className = 'w-10 h-10 rounded-full flex items-center justify-center font-semibold text-[14px] transition-all duration-300';

        if (i < step) {
            circle.className += ' bg-accent text-white';
            num.classList.add('hidden');
            check.classList.remove('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-primary';
        } else if (i === step) {
            circle.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]';
            num.classList.remove('hidden');
            check.classList.add('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-primary';
        } else {
            circle.className += ' bg-surface-2 text-muted border border-th-border';
            num.classList.remove('hidden');
            check.classList.add('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-muted';
        }
    }

    // Progress line
    const progress = document.getElementById('stepper-progress');
    const progressWidth = ((step - 1) / (TOTAL_STEPS - 1)) * 100;
    progress.style.width = `calc((100% - 80px) * ${progressWidth / 100})`;

    // Scroll to top of form
    document.getElementById('registration-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function populateSummary() {
    const form = document.getElementById('registration-form');
    const data = new FormData(form);

    const companyEn = data.get('company_name_en') || '—';
    const city = data.get('city') || '';
    const country = data.get('country') || '';

    document.getElementById('summary-company').textContent = companyEn;
    document.getElementById('summary-location').textContent = [city, country].filter(Boolean).join(', ') || '—';

    // Count uploaded documents
    let docCount = 0;
    if (data.get('trade_license_file')?.size > 0) docCount++;
    if (data.get('tax_certificate_file')?.size > 0) docCount++;
    if (data.get('company_profile_file')?.size > 0) docCount++;
    document.getElementById('summary-docs').textContent = `${docCount} / 3`;

    document.getElementById('summary-manager').textContent = data.get('manager_name') || '—';
}

// On a validation failure the server redirects back here with currentStep
// already set to the offending step. Sync the visible panel to it without
// re-running goToStep's "validate before forward" guard (which would
// bounce the user back to step 1).
if (currentStep !== 1) {
    document.querySelectorAll('[data-step-panel]').forEach(p => {
        p.classList.toggle('hidden', p.dataset.stepPanel != currentStep);
    });
    for (let i = 1; i <= TOTAL_STEPS; i++) {
        const circle = document.querySelector(`[data-step-circle="${i}"]`);
        const num    = document.querySelector(`[data-step-num="${i}"]`);
        const check  = document.querySelector(`[data-step-check="${i}"]`);
        const label  = document.querySelector(`[data-step-label="${i}"]`);
        circle.className = 'w-10 h-10 rounded-full flex items-center justify-center font-semibold text-[14px] transition-all duration-300';
        if (i < currentStep) {
            circle.className += ' bg-accent text-white';
            num.classList.add('hidden');
            check.classList.remove('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-primary';
        } else if (i === currentStep) {
            circle.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]';
            num.classList.remove('hidden');
            check.classList.add('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-primary';
        } else {
            circle.className += ' bg-surface-2 text-muted border border-th-border';
            num.classList.remove('hidden');
            check.classList.add('hidden');
            label.className = 'mt-2 text-[12px] font-medium text-muted';
        }
    }
    const progress = document.getElementById('stepper-progress');
    const progressWidth = ((currentStep - 1) / (TOTAL_STEPS - 1)) * 100;
    progress.style.width = `calc((100% - 80px) * ${progressWidth / 100})`;
    if (currentStep === 3) populateSummary();
}

// Form submits normally to {{ route('register.submit') }} — server handles validation + redirect.
</script>
@endpush

@endsection
