@extends('layouts.app')

@section('title', 'Company Registration - TriLink Trading')

@section('content')

<x-landing.navbar />

<main class="pt-32 pb-20 px-6 lg:px-10 bg-page min-h-screen">
    <div class="max-w-[900px] mx-auto">

        {{-- Header --}}
        <div class="mb-10">
            <h1 class="text-[32px] sm:text-[40px] font-bold text-primary mb-2">Company Registration</h1>
            <p class="text-[15px] text-muted">Register your company to start using TriLink platform</p>
        </div>

        {{-- Stepper --}}
        <div class="mb-10">
            <div class="flex items-start justify-between relative">
                {{-- Connecting lines --}}
                <div class="absolute top-[20px] left-[40px] right-[40px] h-[2px] bg-th-border z-0"></div>
                <div id="stepper-progress" class="absolute top-[20px] left-[40px] h-[2px] bg-accent z-0 transition-all duration-500" style="width: 0;"></div>

                @foreach([1 => 'Company Info', 2 => 'Legal Documents', 3 => 'Manager Details'] as $num => $label)
                <div class="flex flex-col items-center relative z-10">
                    <div data-step-circle="{{ $num }}" class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-[14px] transition-all duration-300 {{ $num === 1 ? 'bg-accent text-white shadow-[0_0_0_4px_rgba(37,99,235,0.15)]' : 'bg-surface-2 text-muted border border-th-border' }}">
                        <span data-step-num="{{ $num }}">{{ $num }}</span>
                        <svg data-step-check="{{ $num }}" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <span data-step-label="{{ $num }}" class="mt-2 text-[12px] font-medium {{ $num === 1 ? 'text-primary' : 'text-muted' }}">{{ $label }}</span>
                </div>
                @endforeach
            </div>
        </div>

        @if ($errors->any())
        <div class="mb-6 bg-[#EF4444]/5 border border-[#EF4444]/30 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#EF4444] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <div class="flex-1">
                    <p class="text-[14px] font-bold text-[#EF4444] mb-1">Please fix the highlighted fields</p>
                    <ul class="list-disc ms-5 space-y-0.5 text-[12px] text-[#EF4444]">
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
            <div data-step-panel="1" class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10">
                {{-- Section header --}}
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-primary">Company Information</h2>
                        <p class="text-[13px] text-muted">Enter your company basic details</p>
                    </div>
                </div>

                {{-- Form fields --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-auth.select label="Company Type" name="company_type" required placeholder="Select what your company does" :options="$companyTypes" />
                    </div>

                    <x-auth.input label="Company Name (English)" name="company_name_en" placeholder="Enter company name" required />
                    <x-auth.input label="Company Name (Arabic)" name="company_name_ar" placeholder="اسم الشركة" dir="rtl" />
                    <x-auth.input label="Trade License Number" name="trade_license" placeholder="e.g., 123456789" required />
                    <x-auth.input label="Tax Registration Number" name="tax_number" placeholder="e.g., TRN-123456789" />
                    <x-auth.select label="Country" name="country" required placeholder="Select country" :options="$countries" />
                    <x-auth.input label="City" name="city" placeholder="e.g., Dubai" required />

                    <div class="md:col-span-2">
                        <x-auth.textarea label="Full Address" name="address" placeholder="Enter complete address" required :rows="3" />
                    </div>

                    <x-auth.input label="Phone Number" name="phone" type="tel" placeholder="+971 50 123 4567" required />
                    <x-auth.input label="Email Address" name="email" type="email" placeholder="info@company.com" required />

                    <div class="md:col-span-2">
                        <x-auth.input label="Website (Optional)" name="website" type="url" placeholder="https://www.company.com" />
                    </div>

                    <div class="md:col-span-2">
                        <x-auth.textarea label="Company Description (Optional)" name="description" placeholder="Tell us about your company, what you offer, and the markets you serve" :rows="4" />
                    </div>
                </div>

                {{-- Step nav --}}
                <div class="mt-10 pt-6 border-t border-th-border flex items-center justify-between">
                    <button type="button" disabled class="px-6 py-2.5 rounded-lg text-[14px] font-medium text-muted bg-surface-2 border border-th-border opacity-50 cursor-not-allowed">Previous</button>
                    <button type="button" onclick="goToStep(2)" class="px-6 py-2.5 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                        Next Step
                    </button>
                </div>
            </div>

            {{-- ============================================
                 STEP 2: LEGAL DOCUMENTS
                 ============================================ --}}
            <div data-step-panel="2" class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10 hidden">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-primary">Legal Documents</h2>
                        <p class="text-[13px] text-muted">Upload required company documents</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <x-auth.upload label="Trade License" description="Upload a copy of your valid trade license" name="trade_license_file" required />
                    <x-auth.upload label="Tax Registration Certificate" description="Upload your tax registration certificate" name="tax_certificate_file" required />
                    <x-auth.upload label="Company Profile" description="Upload company profile or brochure (optional)" name="company_profile_file" />
                </div>

                <div class="mt-10 pt-6 border-t border-th-border flex items-center justify-between">
                    <button type="button" onclick="goToStep(1)" class="px-6 py-2.5 rounded-lg text-[14px] font-semibold text-primary bg-surface-2 border border-th-border hover:bg-elevated transition-colors">Previous</button>
                    <button type="button" onclick="goToStep(3)" class="px-6 py-2.5 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                        Next Step
                    </button>
                </div>
            </div>

            {{-- ============================================
                 STEP 3: MANAGER DETAILS
                 ============================================ --}}
            <div data-step-panel="3" class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10 hidden">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-bold text-primary">Company Manager Details</h2>
                        <p class="text-[13px] text-muted">Information about the authorized manager</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <x-auth.input label="Full Name" name="manager_name" placeholder="Enter manager full name" required />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-auth.input label="Email Address" name="manager_email" type="email" placeholder="manager@company.com" required />
                        <x-auth.input label="Phone Number" name="manager_phone" type="tel" placeholder="+971 50 123 4567" required />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-auth.input label="Password" name="manager_password" type="password" placeholder="Minimum 8 characters" required />
                        <x-auth.input label="Confirm Password" name="manager_password_confirmation" type="password" placeholder="Re-enter your password" required />
                    </div>
                </div>

                {{-- Registration Summary --}}
                <div class="mt-8 bg-surface-2 border border-th-border rounded-xl p-6">
                    <h3 class="text-[16px] font-bold text-primary mb-5">Registration Summary</h3>
                    <dl class="space-y-3">
                        <div class="flex items-center justify-between">
                            <dt class="text-[13px] text-muted">Company Name:</dt>
                            <dd id="summary-company" class="text-[13px] font-medium text-primary">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-[13px] text-muted">Location:</dt>
                            <dd id="summary-location" class="text-[13px] font-medium text-primary">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-[13px] text-muted">Documents Uploaded:</dt>
                            <dd id="summary-docs" class="text-[13px] font-medium text-primary">0 / 3</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-[13px] text-muted">Manager:</dt>
                            <dd id="summary-manager" class="text-[13px] font-medium text-primary">—</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-10 pt-6 border-t border-th-border flex items-center justify-between">
                    <button type="button" onclick="goToStep(2)" class="px-6 py-2.5 rounded-lg text-[14px] font-semibold text-primary bg-surface-2 border border-th-border hover:bg-elevated transition-colors">Previous</button>
                    <button type="submit" class="px-6 py-2.5 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                        Submit for Approval
                    </button>
                </div>
            </div>
        </form>

        {{-- What happens next --}}
        <div class="mt-6 bg-[#22C55E]/5 border border-[#22C55E]/20 rounded-xl p-5 flex items-start gap-3">
            <div class="w-6 h-6 rounded-full bg-[#22C55E]/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-[#22C55E]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary mb-1">What happens next?</h4>
                <p class="text-[13px] text-muted leading-[1.6]">After submission, our admin team will review your company information and documents. You'll receive a notification within 24-48 hours about the approval status.</p>
            </div>
        </div>
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
            circle.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(37,99,235,0.15)]';
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
            circle.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(37,99,235,0.15)]';
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
