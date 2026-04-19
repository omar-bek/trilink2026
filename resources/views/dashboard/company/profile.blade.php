{{--
    Unified Company Profile page.

    Single source of truth for everything related to a company:
    identity, legal & compliance fields, documents, insurances,
    ICV certificates, banking, beneficial owners, branches, team
    and activity.

    Reused by two controllers — the manager-side
    (CompanyProfileController) renders it on /dashboard/company/profile
    and the admin-side (Admin\CompanyController::profile) renders it
    on /admin/companies/{id}/profile. The $mode flag drives the
    admin-only review controls.
--}}
@extends('layouts.dashboard', ['active' => match($mode ?? 'manager') {
    'admin'  => 'admin-companies',
    'public' => 'suppliers-directory',
    default  => 'company-profile',
}])
@section('title', $company->name)

@php
    $mode      = $mode ?? 'manager';
    $isAdmin   = $mode === 'admin';
    $isPublic  = $mode === 'public';
    $isManager = $mode === 'manager';
    // Only the manager of the user's OWN company can edit; admin and
    // public viewers always see the read-only display variant.
    $canEdit   = $isManager && auth()->user()?->hasPermission('team.edit');
    // Sensitive sections (bank details, beneficial owners, payment
    // counters, full team list) are hidden in public mode — a
    // cross-company viewer has no business with that data.
    $showSensitive = !$isPublic;

    // Brand accent — deterministic per company so the hero/initials
    // share the same colour every time without a stored field.
    $palette    = ['#4f7cff', '#00d9b5', '#8B5CF6', '#ffb020', '#ff4d7f', '#14B8A6'];
    $brandColor = $palette[$company->id % count($palette)];

    // Verification level → label + colour. Falls back to "Unverified"
    // when null so the hero badge never goes blank.
    $vLevel = $company->verification_level?->value ?? 'unverified';
    $vColors = [
        'unverified' => '#b4b6c0',
        'bronze'     => '#cd7f32',
        'silver'     => '#c0c0c0',
        'gold'       => '#ffb020',
        'platinum'   => '#00d9b5',
    ];
    $vColor = $vColors[$vLevel] ?? '#b4b6c0';

    // Document status pill helper — re-used across the documents,
    // insurances, and ICV sections so the visual language stays
    // consistent.
    $statusPills = [
        'verified' => ['bg' => 'bg-[#00d9b5]/10', 'border' => 'border-[#00d9b5]/30', 'text' => 'text-[#00d9b5]', 'label' => __('status.verified')],
        'pending'  => ['bg' => 'bg-[#ffb020]/10', 'border' => 'border-[#ffb020]/30', 'text' => 'text-[#ffb020]', 'label' => __('status.pending')],
        'rejected' => ['bg' => 'bg-[#ff4d7f]/10', 'border' => 'border-[#ff4d7f]/30', 'text' => 'text-[#ff4d7f]', 'label' => __('status.rejected')],
        'expired'  => ['bg' => 'bg-muted/10',     'border' => 'border-muted/30',     'text' => 'text-muted',     'label' => __('status.expired')],
    ];
@endphp

@section('content')

@if(session('status'))
<div class="mb-6 rounded-[12px] border border-[#00d9b5]/30 bg-[#00d9b5]/[0.08] px-4 py-3 text-[13px] text-[#00d9b5]">
    {{ session('status') }}
</div>
@endif

@if(isset($errors) && $errors->any())
<div class="mb-6 rounded-[12px] border border-[#ff4d7f]/30 bg-[#ff4d7f]/[0.08] px-4 py-3 text-[13px] text-[#ff4d7f]">
    <ul class="list-disc list-inside space-y-1">
    @foreach($errors->all() as $err)
        <li>{{ $err }}</li>
    @endforeach
    </ul>
</div>
@endif

{{-- ─────────────────────── Hero — company identity strip ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] p-[25px] mb-6 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none opacity-[0.05]" style="background: radial-gradient(circle at 100% 0%, {{ $brandColor }} 0%, transparent 60%);"></div>
    <div class="relative flex items-start gap-5 flex-wrap">

        {{-- Logo / initials avatar --}}
        <div class="relative flex-shrink-0">
            @if($company->logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo) }}"
                     alt="{{ $company->name }}"
                     class="w-24 h-24 rounded-[16px] object-cover border border-th-border bg-page" />
            @else
                <div class="w-24 h-24 rounded-[16px] font-bold flex items-center justify-center text-[32px]"
                     style="background: {{ $brandColor }}1a; color: {{ $brandColor }}; border: 1px solid {{ $brandColor }}40;">
                    {{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}
                </div>
            @endif
            @if($canEdit)
            <form method="POST" action="{{ route('dashboard.company.profile.logo') }}" enctype="multipart/form-data"
                  class="absolute -bottom-1 -right-1 rtl:-right-auto rtl:-left-1">
                @csrf
                <label class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-full bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.4)] hover:bg-accent-h transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <input type="file" name="logo" class="hidden" accept="image/png,image/jpeg,image/webp,image/svg+xml" onchange="this.form.submit()" />
                </label>
            </form>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-wrap mb-2">
                <h2 class="text-[26px] font-bold text-primary leading-tight">{{ $company->name }}</h2>
                <x-dashboard.status-badge :status="$company->status?->value ?? 'pending'" />
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold rounded-full px-2.5 py-1 border"
                      style="color: {{ $vColor }}; background: {{ $vColor }}1a; border-color: {{ $vColor }}40;">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('trust.level_' . $vLevel) }}
                </span>
            </div>
            @if($company->name_ar)
            <p class="text-[16px] text-muted mb-1" dir="rtl">{{ $company->name_ar }}</p>
            @endif
            <p class="text-[12px] text-faint">
                {{ __('role.' . ($company->type?->value ?? 'buyer')) }}
                · {{ $company->registration_number }}
                · {{ trim(($company->city ?? '') . ($company->city && $company->country ? ', ' : '') . ($company->country ?? '')) ?: '—' }}
            </p>
            <div class="mt-4 flex items-center gap-4 flex-wrap text-[12px]">
                @if($company->email)
                <a href="mailto:{{ $company->email }}" class="inline-flex items-center gap-2 text-muted hover:text-accent">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ $company->email }}
                </a>
                @endif
                @if($company->phone)
                <a href="tel:{{ $company->phone }}" class="inline-flex items-center gap-2 text-muted hover:text-accent">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ $company->phone }}
                </a>
                @endif
                @if($company->website)
                <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-accent hover:underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    {{ parse_url($company->website, PHP_URL_HOST) ?: $company->website }}
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ─────────────────────── KPI strip — document vault snapshot ─────────────────────── --}}
@php
    // Public viewers see "trust signals" instead of internal review
    // counts: how many verified docs / insurances / ICV certs the
    // company has, plus its years on the platform. The pending /
    // rejected counters are admin/manager signals only.
    if ($isPublic) {
        $kpis = [
            ['value' => $docStats['verified'],         'label' => __('company_profile.kpi_verified_docs'),    'color' => '#00d9b5'],
            ['value' => $insurances->count(),          'label' => __('company_profile.section_insurances'),   'color' => '#14B8A6'],
            ['value' => $icvCertificates->count(),     'label' => __('company_profile.section_icv'),          'color' => '#ffb020'],
            ['value' => $branches->count(),            'label' => __('company_profile.kpi_branches'),         'color' => '#8B5CF6'],
            ['value' => $company->created_at ? max(1, (int) $company->created_at->diffInYears(now())) : 0,
             'label' => __('company_profile.kpi_years_active'), 'color' => '#4f7cff'],
        ];
    } else {
        $kpis = [
            ['value' => $docStats['total'],    'label' => __('company_profile.kpi_total_docs'),    'color' => '#4f7cff'],
            ['value' => $docStats['verified'], 'label' => __('company_profile.kpi_verified_docs'), 'color' => '#00d9b5'],
            ['value' => $docStats['pending'],  'label' => __('company_profile.kpi_pending_docs'),  'color' => '#ffb020'],
            ['value' => $docStats['rejected'], 'label' => __('company_profile.kpi_rejected_docs'), 'color' => '#ff4d7f'],
            ['value' => $branches->count(),    'label' => __('company_profile.kpi_branches'),      'color' => '#8B5CF6'],
        ];
    }
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @foreach($kpis as $k)
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold leading-[32px] tracking-[0.003em] truncate" style="color: {{ $k['color'] }};">{{ number_format((int) $k['value']) }}</p>
        <p class="text-[12px] text-muted leading-[18px] mt-1">{{ $k['label'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ─────────────────────── LEFT — main content ─────────────────────── --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- ─────────────── Identity & contact (editable form for manager) ─────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-9 4h12a2 2 0 002-2V7a2 2 0 00-2-2h-2.382a1 1 0 01-.894-.553L11 2H6a2 2 0 00-2 2v15a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_identity') }}</h3>
            </div>

            @if($canEdit)
            {{-- Editable form (manager) --}}
            <form method="POST" action="{{ route('dashboard.company.profile.update') }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-[13px]">
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_name_en') }}</label>
                        <input type="text" name="name" required value="{{ old('name', $company->name) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_name_ar') }}</label>
                        <input type="text" name="name_ar" value="{{ old('name_ar', $company->name_ar) }}" dir="rtl"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $company->email) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_phone') }}</label>
                        <input type="tel" name="phone" value="{{ old('phone', $company->phone) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_website') }}</label>
                        <input type="url" name="website" value="{{ old('website', $company->website) }}" placeholder="https://"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $company->city) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_country') }}</label>
                        <input type="text" name="country" value="{{ old('country', $company->country) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_address') }}</label>
                        <input type="text" name="address" value="{{ old('address', $company->address) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_description') }}</label>
                        <textarea name="description" rows="4"
                                  class="w-full bg-page border border-th-border rounded-[10px] px-3 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors resize-none">{{ old('description', $company->description) }}</textarea>
                    </div>

                    {{-- Phase 3 — Free zone & legal jurisdiction --}}
                    <div class="md:col-span-2 pt-4 mt-2 border-t border-th-border">
                        <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('company_profile.section_jurisdiction') }}</p>
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2 text-[12px] text-primary cursor-pointer">
                            <input type="checkbox" name="is_free_zone" value="1" @checked(old('is_free_zone', (bool) $company->is_free_zone)) />
                            {{ __('company_profile.field_is_free_zone') }}
                        </label>
                    </div>
                    <div></div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_free_zone_authority') }}</label>
                        <select name="free_zone_authority"
                                class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors">
                            <option value="">— {{ __('common.none') }} —</option>
                            @foreach($freeZones as $fz)
                                <option value="{{ $fz->value }}" @selected(old('free_zone_authority', $company->free_zone_authority?->value) === $fz->value)>{{ $fz->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_jurisdiction') }}</label>
                        <select name="legal_jurisdiction"
                                class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors">
                            @foreach($jurisdictions as $j)
                                <option value="{{ $j->value }}" @selected(old('legal_jurisdiction', $company->legal_jurisdiction?->value ?? 'federal') === $j->value)>{{ $j->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('company_profile.field_tax_number') }}</label>
                        <input type="text" name="tax_number" value="{{ old('tax_number', $company->tax_number) }}"
                               class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary font-mono focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
                    </div>
                    <div></div>
                </div>
                <div class="mt-5 flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 bg-accent text-white rounded-[10px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('common.save_changes') }}
                    </button>
                </div>
            </form>
            @else
            {{-- Read-only display (admin or non-manager team member) --}}
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-[13px]">
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_name_en') }}</dt><dd class="text-primary mt-1.5 font-semibold">{{ $company->name }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_name_ar') }}</dt><dd class="text-primary mt-1.5" dir="rtl">{{ $company->name_ar ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_email') }}</dt><dd class="text-primary mt-1.5">{{ $company->email ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_phone') }}</dt><dd class="text-primary mt-1.5">{{ $company->phone ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_website') }}</dt><dd class="text-primary mt-1.5">{{ $company->website ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_tax_number') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $company->tax_number ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_address') }}</dt><dd class="text-primary mt-1.5">{{ trim(($company->address ?? '') . ', ' . ($company->city ?? '') . ', ' . ($company->country ?? ''), ', ') ?: '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_description') }}</dt><dd class="text-body mt-1.5 whitespace-pre-line leading-relaxed">{{ $company->description ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_jurisdiction') }}</dt><dd class="text-primary mt-1.5">{{ $company->legal_jurisdiction?->label() ?? __('jurisdiction.federal') }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.field_free_zone_authority') }}</dt><dd class="text-primary mt-1.5">{{ $company->free_zone_authority?->label() ?? '—' }}</dd></div>
            </dl>
            @endif
        </div>

        {{-- ─────────────── Categories (admin-approved) ─────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#14B8A6]/10 border border-[#14B8A6]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#14B8A6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">
                        {{ __('company_profile.section_categories') }}
                        <span class="text-muted text-[12px] font-medium">({{ $company->categories->count() }})</span>
                    </h3>
                </div>
            </div>

            <p class="text-[12px] text-muted mb-4 leading-relaxed">{{ __('company_profile.categories_help') }}</p>

            @if($company->categories->isEmpty())
                <div class="rounded-[12px] border border-dashed border-th-border px-4 py-6 text-center mb-5">
                    <p class="text-[13px] text-muted">{{ __('company_profile.no_categories') }}</p>
                </div>
            @else
                <div class="flex flex-wrap gap-2 mb-5">
                    @foreach($company->categories as $cat)
                        <span class="inline-flex items-center gap-1.5 px-3 h-8 rounded-full bg-[#14B8A6]/10 border border-[#14B8A6]/25 text-[#14B8A6] text-[12px] font-semibold">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ app()->getLocale() === 'ar' && $cat->name_ar ? $cat->name_ar : $cat->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if($isManager && isset($pendingCategoryRequests) && $pendingCategoryRequests->isNotEmpty())
                <div class="space-y-2 mb-5">
                    @foreach($pendingCategoryRequests as $req)
                        @php
                            $catLabel = $req->category ? (app()->getLocale() === 'ar' && $req->category->name_ar ? $req->category->name_ar : $req->category->name) : '—';
                            $isPending = $req->status === \App\Models\CompanyCategoryRequest::STATUS_PENDING;
                            $pillBg   = $isPending ? 'bg-[#ffb020]/10 border-[#ffb020]/25 text-[#ffb020]' : 'bg-[#ff4d7f]/10 border-[#ff4d7f]/25 text-[#ff4d7f]';
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-[12px] border border-th-border bg-surface-2 px-3 py-2.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center gap-1.5 px-2.5 h-6 rounded-full border text-[10px] font-bold uppercase tracking-wider {{ $pillBg }}">
                                    {{ __('company_profile.category_status_' . $req->status) }}
                                </span>
                                <span class="text-[13px] font-semibold text-primary truncate">{{ $catLabel }}</span>
                                @if($req->rejection_reason)
                                    <span class="text-[11px] text-muted italic truncate">— {{ $req->rejection_reason }}</span>
                                @endif
                            </div>
                            @if($isPending && $canEdit)
                                <form method="POST" action="{{ route('dashboard.company.profile.categories.cancel', $req->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-[11px] font-semibold text-muted hover:text-[#ff4d7f] transition-colors">
                                        {{ __('company_profile.category_request_cancel') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($canEdit && isset($availableCategories) && $availableCategories->isNotEmpty())
                <form method="POST" action="{{ route('dashboard.company.profile.categories.request') }}"
                      class="rounded-[12px] border border-th-border bg-surface-2 p-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-faint mb-2">
                            {{ __('company_profile.category_request_label') }} <span class="text-[#ff4d7f] normal-case">*</span>
                        </label>
                        <select name="category_id" required
                                class="w-full bg-surface border border-th-border rounded-[10px] px-3 h-11 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20">
                            <option value="">— {{ __('company_profile.category_request_placeholder') }} —</option>
                            @foreach($availableCategories as $cat)
                                <option value="{{ $cat->id }}">
                                    {{ str_repeat('— ', (int) $cat->level) }}{{ app()->getLocale() === 'ar' && $cat->name_ar ? $cat->name_ar : $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-faint mb-2">
                            {{ __('company_profile.category_request_note') }}
                        </label>
                        <textarea name="note" rows="2" maxlength="1000"
                                  placeholder="{{ __('company_profile.category_request_note_placeholder') }}"
                                  class="w-full bg-surface border border-th-border rounded-[10px] px-3 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 resize-none"></textarea>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-2 h-10 px-4 bg-accent text-white rounded-[10px] text-[12px] font-bold hover:bg-accent-h transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        {{ __('company_profile.category_request_submit') }}
                    </button>
                </form>
            @endif
        </div>

        {{-- ─────────────── Documents vault ─────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_documents') }} <span class="text-muted text-[12px] font-medium">({{ $documents->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('dashboard.documents.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('company_profile.upload_document') }}
                </a>
                @endif
            </div>

            @if($documents->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <svg class="w-10 h-10 text-faint mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-[13px] text-muted">{{ __('company_profile.no_documents') }}</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($documents as $doc)
                @php
                    $statusKey = $doc->isExpired() ? 'expired' : ($doc->status ?? 'pending');
                    $pill = $statusPills[$statusKey] ?? $statusPills['pending'];
                @endphp
                <div class="bg-page border border-th-border rounded-[12px] p-4 hover:border-accent/30 transition-colors">
                    <div class="flex items-start gap-3 flex-wrap">
                        <div class="w-10 h-10 rounded-[10px] bg-surface-2 border border-th-border flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <p class="text-[13px] font-semibold text-primary truncate">{{ $doc->label ?: __('trust.doc_' . ($doc->type?->value ?? 'other')) }}</p>
                                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold rounded-full px-2 py-0.5 border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }}">
                                    {{ $pill['label'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-faint">
                                @if($doc->original_filename){{ $doc->original_filename }} · @endif
                                @if($doc->expires_at){{ __('company_profile.expires_on') }} {{ $doc->expires_at->format('M j, Y') }} · @endif
                                {{ __('company_profile.uploaded_on') }} {{ $doc->created_at?->format('M j, Y') }}
                            </p>
                            @if($doc->status === 'rejected' && $doc->rejection_reason)
                            <p class="text-[11px] text-[#ff4d7f] mt-1">{{ __('company_profile.rejection_reason') }}: {{ $doc->rejection_reason }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($isAdmin && $doc->file_path)
                                <a href="{{ route('admin.documents.download', $doc->id) }}"
                                   class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-accent/10 border border-accent/30 text-accent text-[11px] font-bold hover:bg-accent/20 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    {{ __('common.download') }}
                                </a>
                            @endif
                            @if($isAdmin && $doc->status === 'pending')
                                <form method="POST" action="{{ route('admin.documents.review', $doc->id) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="verify" />
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] text-[11px] font-bold hover:bg-[#00d9b5]/20 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        {{ __('common.verify') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.documents.review', $doc->id) }}" class="inline"
                                      onsubmit="this.querySelector('input[name=reason]').value = prompt('{{ __('company_profile.rejection_reason_prompt') }}') || ''; return this.querySelector('input[name=reason]').value !== '';">
                                    @csrf
                                    <input type="hidden" name="action" value="reject" />
                                    <input type="hidden" name="reason" value="" />
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[11px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        {{ __('common.reject') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ─────────────── Insurance policies ─────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#14B8A6]/10 border border-[#14B8A6]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#14B8A6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_insurances') }} <span class="text-muted text-[12px] font-medium">({{ $insurances->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('dashboard.insurances.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('company_profile.add_insurance') }}
                </a>
                @endif
            </div>

            @if($insurances->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('company_profile.no_insurances') }}</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($insurances as $policy)
                @php $pill = $statusPills[$policy->status] ?? $statusPills['pending']; @endphp
                <div class="bg-page border border-th-border rounded-[12px] p-4 flex items-start gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <p class="text-[13px] font-semibold text-primary truncate">{{ $policy->insurer }}</p>
                            <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold rounded-full px-2 py-0.5 border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }}">
                                {{ $pill['label'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-faint">
                            {{ __('company_profile.policy_number') }}: {{ $policy->policy_number }}
                            · {{ __('company_profile.coverage') }}: {{ $policy->currency }} {{ number_format((float) $policy->coverage_amount) }}
                            @if($policy->expires_at) · {{ __('company_profile.expires_on') }} {{ $policy->expires_at->format('M j, Y') }}@endif
                        </p>
                        @if($policy->status === 'rejected' && $policy->rejection_reason)
                        <p class="text-[11px] text-[#ff4d7f] mt-1">{{ __('company_profile.rejection_reason') }}: {{ $policy->rejection_reason }}</p>
                        @endif
                    </div>
                    @if($isAdmin && $policy->status === 'pending')
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <form method="POST" action="{{ route('admin.insurances.review', $policy->id) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="verify" />
                                <button type="submit"
                                        class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] text-[11px] font-bold hover:bg-[#00d9b5]/20 transition-colors">
                                    {{ __('common.verify') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.insurances.review', $policy->id) }}" class="inline"
                                  onsubmit="this.querySelector('input[name=reason]').value = prompt('{{ __('company_profile.rejection_reason_prompt') }}') || ''; return this.querySelector('input[name=reason]').value !== '';">
                                @csrf
                                <input type="hidden" name="action" value="reject" />
                                <input type="hidden" name="reason" value="" />
                                <button type="submit"
                                        class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[11px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                                    {{ __('common.reject') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ─────────────── ICV certificates (suppliers only show non-empty) ─────────────── --}}
        @if($icvCertificates->isNotEmpty() || in_array($company->type?->value, ['supplier', 'service_provider', 'logistics', 'clearance'], true))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_icv') }} <span class="text-muted text-[12px] font-medium">({{ $icvCertificates->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('dashboard.icv-certificates.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('company_profile.add_icv') }}
                </a>
                @endif
            </div>

            @if($icvCertificates->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('company_profile.no_icv') }}</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($icvCertificates as $cert)
                @php $pill = $statusPills[$cert->status] ?? $statusPills['pending']; @endphp
                <div class="bg-page border border-th-border rounded-[12px] p-4 flex items-start gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <p class="text-[13px] font-semibold text-primary truncate">{{ strtoupper($cert->issuer) }} · {{ number_format((float) $cert->score, 2) }}%</p>
                            <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold rounded-full px-2 py-0.5 border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }}">
                                {{ $pill['label'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-faint">
                            {{ __('company_profile.cert_number') }}: <span class="font-mono">{{ $cert->certificate_number }}</span>
                            · {{ __('company_profile.expires_on') }} {{ $cert->expires_date?->format('M j, Y') }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- ─────────────── Compliance certificates (Phase 8 — Tier 3) ─────────────── --}}
        @if(isset($certificateUploads) && ($certificateUploads->isNotEmpty() || $isAdmin))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#14B8A6]/10 border border-[#14B8A6]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#14B8A6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('admin.tabs.certificate_uploads') }} <span class="text-muted text-[12px] font-medium">({{ $certificateUploads->count() }})</span></h3>
                </div>
                @if($isAdmin)
                <a href="{{ route('admin.certificate-uploads.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    {{ __('common.manage') }}
                </a>
                @endif
            </div>

            @if($certificateUploads->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('cert_upload.empty_admin') }}</p>
            </div>
            @else
            <div class="space-y-2.5">
                @foreach($certificateUploads as $cu)
                @php $pill = $statusPills[$cu->status] ?? $statusPills['pending']; @endphp
                <div class="bg-page border border-th-border rounded-[12px] p-4 flex items-start gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold bg-accent/10 border border-accent/20 text-accent uppercase">
                                {{ __('cert_upload.type_' . $cu->certificate_type) }}
                            </span>
                            <p class="text-[13px] font-semibold text-primary truncate">{{ $cu->issuer ?? $cu->certificate_number }}</p>
                            <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold rounded-full px-2 py-0.5 border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }}">
                                {{ $pill['label'] }}
                            </span>
                        </div>
                        <p class="text-[11px] text-faint">
                            @if($cu->certificate_number){{ __('company_profile.cert_number') }}: <span class="font-mono">{{ $cu->certificate_number }}</span> · @endif
                            @if($cu->expires_date){{ __('company_profile.expires_on') }} {{ $cu->expires_date->format('M j, Y') }} · @endif
                            @if($cu->issued_date){{ __('cert_upload.col_issuer') }}: {{ $cu->issued_date->format('M j, Y') }}@endif
                        </p>
                        @if($cu->status === 'rejected' && $cu->rejection_reason)
                        <p class="text-[11px] text-[#ff4d7f] mt-1">{{ __('company_profile.rejection_reason') }}: {{ $cu->rejection_reason }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($isAdmin && $cu->file_path)
                            <a href="{{ route('admin.certificate-uploads.download', $cu->id) }}"
                               class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-accent/10 border border-accent/30 text-accent text-[11px] font-bold hover:bg-accent/20 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ __('common.download') }}
                            </a>
                        @endif
                        @if($isAdmin && $cu->status === 'pending')
                            <form method="POST" action="{{ route('admin.certificate-uploads.approve', $cu->id) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] text-[11px] font-bold hover:bg-[#00d9b5]/20 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ __('cert_upload.approve') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.certificate-uploads.reject', $cu->id) }}" class="inline"
                                  onsubmit="const r = prompt('{{ __('cert_upload.rejection_prompt') }}'); if (!r) return false; this.reason.value = r;">
                                @csrf
                                <input type="hidden" name="reason">
                                <button type="submit"
                                        class="inline-flex items-center gap-1 h-8 px-3 rounded-[8px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[11px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ __('cert_upload.reject') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- ─────────────── Beneficial owners (hidden in public mode) ─────────────── --}}
        @if($showSensitive && ($company->beneficialOwners->isNotEmpty() || $isManager))
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_beneficial_owners') }} <span class="text-muted text-[12px] font-medium">({{ $company->beneficialOwners->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('dashboard.beneficial-owners.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('common.manage') }}
                </a>
                @endif
            </div>

            @if($company->beneficialOwners->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('company_profile.no_beneficial_owners') }}</p>
            </div>
            @else
            <div class="space-y-2">
                @foreach($company->beneficialOwners as $bo)
                <div class="bg-page border border-th-border rounded-[10px] px-4 py-3 flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-primary truncate">{{ $bo->full_name }}</p>
                        <p class="text-[11px] text-faint">
                            {{ __('company_profile.role_label') }}: {{ ucfirst($bo->role) }}
                            @if($bo->nationality) · {{ $bo->nationality }}@endif
                            @if($bo->is_pep) · <span class="text-[#ffb020] font-semibold">PEP</span>@endif
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="text-[18px] font-bold text-accent leading-tight">{{ number_format((float) $bo->ownership_percentage, 1) }}%</p>
                        <p class="text-[10px] text-faint uppercase tracking-wider">{{ __('company_profile.ownership') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- ─────────────── Branches ─────────────── --}}
        @if($branches->isNotEmpty() || $isManager)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_branches') }} <span class="text-muted text-[12px] font-medium">({{ $branches->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('dashboard.branches.index') }}"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-[10px] bg-accent/10 border border-accent/20 text-accent text-[12px] font-bold hover:bg-accent/20 transition-colors">
                    {{ __('common.manage') }}
                </a>
                @endif
            </div>

            @if($branches->isEmpty())
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('company_profile.no_branches') }}</p>
            </div>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($branches as $branch)
                <div class="bg-page border border-th-border rounded-[10px] px-4 py-3">
                    <p class="text-[13px] font-semibold text-primary">{{ $branch->name }}</p>
                    <p class="text-[11px] text-faint mt-0.5">{{ trim(($branch->city ?? '') . ', ' . ($branch->country ?? ''), ', ') ?: '—' }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- ─────────────── Reviews & ratings (public mode only) ─────────────── --}}
        {{--
            Only the cross-company surface (SupplierProfileController::show)
            passes a $reviews variable in. The manager and admin paths
            don't compute reviews so this whole block stays hidden via
            the isset() guard.
        --}}
        @if(isset($reviews) && $isPublic)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-bold text-primary">{{ __('company_profile.section_reviews') }} <span class="text-muted text-[12px] font-medium">({{ $review_count ?? 0 }})</span></h3>
                </div>
                @if($rating)
                <div class="flex items-center gap-2">
                    <span class="text-[24px] font-bold text-[#ffb020] leading-none">{{ number_format((float) $rating, 1) }}</span>
                    <span class="text-[11px] text-muted">/ 5</span>
                </div>
                @endif
            </div>

            @if(empty($reviews))
            <div class="rounded-[12px] border border-dashed border-th-border bg-page/40 px-6 py-10 text-center">
                <p class="text-[13px] text-muted">{{ __('company_profile.no_reviews') }}</p>
            </div>
            @else
            {{-- Score breakdown strip — quality / on-time / communication --}}
            @if(!empty($breakdown))
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                @foreach([
                    ['label' => __('company_profile.review_quality'),       'value' => $breakdown['quality']       ?? null],
                    ['label' => __('company_profile.review_on_time'),       'value' => $breakdown['on_time']       ?? null],
                    ['label' => __('company_profile.review_communication'), 'value' => $breakdown['communication'] ?? null],
                ] as $b)
                <div class="bg-page border border-th-border rounded-[10px] px-4 py-3">
                    <p class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ $b['label'] }}</p>
                    <p class="text-[18px] font-bold text-primary mt-1">{{ $b['value'] !== null ? number_format((float) $b['value'], 1) : '—' }}</p>
                </div>
                @endforeach
            </div>
            @endif

            <div class="space-y-2.5">
                @foreach(array_slice($reviews, 0, 5) as $rev)
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
                        <p class="text-[13px] font-semibold text-primary">{{ $rev['rater_company'] }}</p>
                        <div class="flex items-center gap-1 text-[#ffb020]">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-3.5 h-3.5 {{ $i <= (int) $rev['rating'] ? '' : 'opacity-20' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            @endfor
                        </div>
                    </div>
                    @if($rev['comment'])
                    <p class="text-[12px] text-body leading-relaxed">{{ $rev['comment'] }}</p>
                    @endif
                    <p class="text-[10px] text-faint mt-2">{{ $rev['contract'] }} · {{ $rev['when'] }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- ─────────────────────── RIGHT — sidebar ─────────────────────── --}}
    <div class="space-y-6">

        {{-- ─────────────── Admin actions (admin mode only) ─────────────── --}}
        @if($isAdmin)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('company_profile.admin_actions') }}</h3>
            </div>

            <div class="space-y-2">
                @if($company->status?->value === 'pending')
                <form method="POST" action="{{ route('admin.companies.approve', $company->id) }}">@csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[10px] bg-[#00d9b5] text-white text-[13px] font-bold hover:brightness-110 shadow-[0_4px_14px_rgba(0,217,181,0.25)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('admin.companies.approve') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.companies.reject', $company->id) }}">@csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[13px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                        {{ __('admin.companies.reject') }}
                    </button>
                </form>
                @elseif($company->status?->value === 'active')
                <form method="POST" action="{{ route('admin.companies.suspend', $company->id) }}">@csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/30 text-[#ffb020] text-[13px] font-bold hover:bg-[#ffb020]/20 transition-colors">
                        {{ __('admin.companies.suspend') }}
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.companies.reactivate', $company->id) }}">@csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[10px] bg-[#00d9b5] text-white text-[13px] font-bold hover:brightness-110 shadow-[0_4px_14px_rgba(0,217,181,0.25)] transition-all">
                        {{ __('admin.companies.reactivate') }}
                    </button>
                </form>
                @endif
            </div>

            <div class="mt-5 pt-5 border-t border-th-border">
                <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('company_profile.set_verification_level') }}</p>
                <form method="POST" action="{{ route('admin.companies.set-verification', $company->id) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="verification_level"
                            class="flex-1 bg-page border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors">
                        @foreach(['unverified', 'bronze', 'silver', 'gold', 'platinum'] as $lvl)
                            <option value="{{ $lvl }}" @selected($company->verification_level?->value === $lvl)>{{ __('trust.level_' . $lvl) }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="inline-flex items-center justify-center h-10 px-3 rounded-[10px] bg-accent text-white text-[12px] font-bold hover:bg-accent-h transition-colors">
                        {{ __('common.save') }}
                    </button>
                </form>
            </div>

            <div class="mt-5 pt-5 border-t border-th-border space-y-2">
                <a href="{{ route('admin.companies.show', $company->id) }}"
                   class="block text-center text-[12px] font-semibold text-muted hover:text-accent transition-colors">
                    {{ __('company_profile.legacy_admin_view') }}
                </a>
            </div>
        </div>

        {{-- ─────────────── Request More Info (admin mode only) ─────────────── --}}
        @php
            $infoRequestRow  = $company->infoRequest;
            $existingRequest = $infoRequestRow && $infoRequestRow->isPending()
                ? [
                    'items' => $infoRequestRow->items ?? [],
                    'note'  => $infoRequestRow->note ?? '',
                ]
                : null;
            $catalog         = \App\Support\CompanyInfoFields::catalog();
            $fieldEntries    = array_filter($catalog, fn ($e) => ($e['kind'] ?? '') === 'field');
            $docEntries      = array_filter($catalog, fn ($e) => ($e['kind'] ?? '') === 'document');
            $checkedItems    = $existingRequest['items'] ?? [];
        @endphp
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.companies.request_info_title') }}</h3>
                        <p class="text-[11px] text-muted mt-0.5">{{ __('admin.companies.request_info_help') }}</p>
                    </div>
                </div>
                @if($existingRequest)
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/30 rounded-full px-2.5 py-1 flex-shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ffb020]"></span>
                    {{ __('admin.companies.request_info_active') }}
                </span>
                @endif
            </div>

            @if($existingRequest)
            <div class="bg-surface-2 border border-th-border rounded-[12px] p-[17px] mb-5 text-[12px]">
                <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-3">{{ __('admin.companies.request_info_pending') }}</p>
                <ul class="space-y-1.5 text-body mb-3">
                    @foreach($existingRequest['items'] as $key)
                        @if(isset($catalog[$key]))
                        <li class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __($catalog[$key]['label_key']) }}
                        </li>
                        @endif
                    @endforeach
                </ul>
                @if(!empty($existingRequest['note']))
                <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('admin.companies.request_info_note') }}</p>
                <p class="text-body whitespace-pre-line">{{ $existingRequest['note'] }}</p>
                @endif
                <form method="POST" action="{{ route('admin.companies.cancel-info', $company->id) }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[11px] font-semibold text-[#ff4d7f] hover:underline">{{ __('admin.companies.request_info_cancel') }}</button>
                </form>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.companies.request-info', $company->id) }}">
                @csrf
                <div class="mb-5">
                    <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('admin.companies.request_info_fields') }}</p>
                    <div class="space-y-2">
                        @foreach($fieldEntries as $key => $entry)
                        <label class="flex items-center gap-2 text-[12px] text-body bg-surface-2 border border-th-border rounded-[10px] px-3 py-2 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="checkbox" name="items[]" value="{{ $key }}" @checked(in_array($key, $checkedItems, true)) />
                            {{ __($entry['label_key']) }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-5">
                    <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('admin.companies.request_info_documents') }}</p>
                    <div class="space-y-2">
                        @foreach($docEntries as $key => $entry)
                        <label class="flex items-center gap-2 text-[12px] text-body bg-surface-2 border border-th-border rounded-[10px] px-3 py-2 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="checkbox" name="items[]" value="{{ $key }}" @checked(in_array($key, $checkedItems, true)) />
                            {{ __($entry['label_key']) }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-2">{{ __('admin.companies.request_info_note_label') }}</label>
                    <textarea name="note" rows="3"
                              placeholder="{{ __('admin.companies.request_info_note_placeholder') }}"
                              class="w-full bg-surface-2 border border-th-border rounded-[12px] px-4 py-3 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 resize-none transition-colors">{{ $existingRequest['note'] ?? '' }}</textarea>
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    {{ $existingRequest ? __('admin.companies.request_info_update') : __('admin.companies.request_info_send') }}
                </button>
            </form>
        </div>
        @endif

        {{-- ─────────────── Bank details (hidden in public mode) ─────────────── --}}
        @if($showSensitive)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('company_profile.section_bank') }}</h3>
            </div>
            @if($company->bankDetails)
            <dl class="text-[12px] space-y-3">
                <div>
                    <dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.bank_holder') }}</dt>
                    <dd class="text-primary mt-1">{{ $company->bankDetails->holder_name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.bank_name') }}</dt>
                    <dd class="text-primary mt-1">{{ $company->bankDetails->bank_name }} · {{ $company->bankDetails->branch }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.bank_iban') }}</dt>
                    <dd class="text-primary mt-1 font-mono text-[11px]">{{ $company->bankDetails->iban }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('company_profile.bank_swift') }}</dt>
                    <dd class="text-primary mt-1 font-mono text-[11px]">{{ $company->bankDetails->swift }}</dd>
                </div>
            </dl>
            @else
            <p class="text-[12px] text-muted">{{ __('company_profile.no_bank') }}</p>
            @endif
        </div>
        @endif

        {{-- ─────────────── Manager card ─────────────── --}}
        @if($manager)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('company_profile.section_manager') }}</h3>
            </div>
            <p class="text-[13px] font-semibold text-primary">{{ trim(($manager->first_name ?? '') . ' ' . ($manager->last_name ?? '')) }}</p>
            <a href="mailto:{{ $manager->email }}" class="text-[12px] text-muted hover:text-accent block mt-1">{{ $manager->email }}</a>
            @if($manager->phone)
            <a href="tel:{{ $manager->phone }}" class="text-[12px] text-muted hover:text-accent block mt-0.5">{{ $manager->phone }}</a>
            @endif
        </div>
        @endif

        {{-- ─────────────── Team (hidden in public mode) ─────────────── --}}
        @if($showSensitive)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="text-[14px] font-bold text-primary">{{ __('company_profile.section_team') }} <span class="text-muted text-[11px] font-medium">({{ $company->users->count() }})</span></h3>
                </div>
                @if($isManager)
                <a href="{{ route('company.users') }}" class="text-[11px] font-semibold text-accent hover:underline">{{ __('common.manage') }}</a>
                @endif
            </div>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @foreach($company->users->take(8) as $u)
                <div class="flex items-center gap-3 px-1">
                    <div class="w-8 h-8 rounded-full font-bold flex items-center justify-center text-[10px] flex-shrink-0 bg-accent/10 text-accent border border-accent/20">
                        {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12px] font-semibold text-primary truncate">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                        <p class="text-[10px] text-muted truncate">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</p>
                    </div>
                </div>
                @endforeach
                @if($company->users->count() > 8)
                <p class="text-[11px] text-muted pt-2 text-center">+ {{ $company->users->count() - 8 }} {{ __('common.more') }}</p>
                @endif
            </div>
        </div>
        @endif

        {{-- ─────────────── Activity (hidden in public mode) ─────────────── --}}
        @if($showSensitive)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#14B8A6]/10 border border-[#14B8A6]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#14B8A6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('company_profile.section_activity') }}</h3>
            </div>
            <dl class="text-[12px] space-y-3">
                @foreach([
                    ['label' => __('nav.purchase_requests'), 'count' => $activity['purchase_requests'], 'color' => '#4f7cff'],
                    ['label' => __('nav.rfqs'),              'count' => $activity['rfqs'],              'color' => '#8B5CF6'],
                    ['label' => __('nav.bids'),              'count' => $activity['bids'],              'color' => '#00d9b5'],
                    ['label' => __('nav.contracts'),         'count' => $activity['contracts'],         'color' => '#ffb020'],
                    ['label' => __('nav.payment_management'),'count' => $activity['payments'],          'color' => '#14B8A6'],
                ] as $row)
                <div class="flex items-center justify-between gap-3">
                    <dt class="flex items-center gap-2 text-muted">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $row['color'] }};"></span>
                        {{ $row['label'] }}
                    </dt>
                    <dd class="text-primary font-bold">{{ number_format((int) $row['count']) }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
        @endif
    </div>
</div>

@endsection
