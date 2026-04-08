@extends('layouts.dashboard', ['active' => 'admin-companies'])
@section('title', __('admin.companies.edit'))

@section('content')

<x-dashboard.page-header :title="__('admin.companies.edit')" :subtitle="$company->name" :back="route('admin.companies.show', $company->id)" />

<x-admin.navbar active="companies" />

@php
$inputCls = 'w-full bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors';
$labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-faint mb-2';
@endphp

<form method="POST" action="{{ route('admin.companies.update', $company->id) }}" class="bg-surface border border-th-border rounded-[16px] p-[25px] max-w-4xl">
    @csrf @method('PATCH')

    <div class="space-y-8">
        {{-- ─────────────────────── Section: Identity ─────────────────────── --}}
        <div>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/></svg>
                </div>
                <div>
                    <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.companies.profile') }}</h4>
                    <p class="text-[11px] text-muted">{{ __('admin.companies.section.identity_help') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.name') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required class="{{ $inputCls }}" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.name_ar') }}</label>
                    <input type="text" name="name_ar" value="{{ old('name_ar', $company->name_ar) }}" class="{{ $inputCls }}" dir="rtl" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.registration_number') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                    <input type="text" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" required class="{{ $inputCls }} font-mono" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.tax_number') }}</label>
                    <input type="text" name="tax_number" value="{{ old('tax_number', $company->tax_number) }}" class="{{ $inputCls }} font-mono" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.type') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                    <select name="type" required class="{{ $inputCls }}">
                        @foreach(\App\Enums\CompanyType::cases() as $t)
                            <option value="{{ $t->value }}" @selected(old('type', $company->type?->value) === $t->value)>{{ __('role.' . $t->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('common.status') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                    <select name="status" required class="{{ $inputCls }}">
                        @foreach(\App\Enums\CompanyStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('status', $company->status?->value) === $s->value)>{{ __('status.' . $s->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="border-t border-th-border"></div>

        {{-- ─────────────────────── Section: Contact ─────────────────────── --}}
        <div>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.companies.section.contact') }}</h4>
                    <p class="text-[11px] text-muted">{{ __('admin.companies.section.contact_help') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.users.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $company->email) }}" class="{{ $inputCls }}" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.users.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" class="{{ $inputCls }}" />
                </div>
                <div class="md:col-span-2">
                    <label class="{{ $labelCls }}">{{ __('admin.companies.website') }}</label>
                    <input type="url" name="website" value="{{ old('website', $company->website) }}" placeholder="https://example.com" class="{{ $inputCls }}" />
                </div>
            </div>
        </div>

        <div class="border-t border-th-border"></div>

        {{-- ─────────────────────── Section: Location ─────────────────────── --}}
        <div>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.companies.section.location') }}</h4>
                    <p class="text-[11px] text-muted">{{ __('admin.companies.section.location_help') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.city') }}</label>
                    <input type="text" name="city" value="{{ old('city', $company->city) }}" class="{{ $inputCls }}" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('admin.companies.country') }}</label>
                    <input type="text" name="country" value="{{ old('country', $company->country) }}" class="{{ $inputCls }}" />
                </div>
                <div class="md:col-span-2">
                    <label class="{{ $labelCls }}">{{ __('admin.companies.address') }}</label>
                    <textarea name="address" rows="2" class="{{ str_replace('h-11', 'min-h-[80px] py-3', $inputCls) }} resize-none">{{ old('address', $company->address) }}</textarea>
                </div>
            </div>
        </div>

        <div class="border-t border-th-border"></div>

        {{-- ─────────────────────── Section: About ─────────────────────── --}}
        <div>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.companies.section.about') }}</h4>
                    <p class="text-[11px] text-muted">{{ __('admin.companies.section.about_help') }}</p>
                </div>
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.companies.description') }}</label>
                <textarea name="description" rows="4" class="{{ str_replace('h-11', 'min-h-[120px] py-3', $inputCls) }} resize-none">{{ old('description', $company->description) }}</textarea>
            </div>

            @if($categories->isNotEmpty())
            <div class="mt-5">
                <label class="{{ $labelCls }}">{{ __('admin.companies.categories') }}</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-56 overflow-y-auto bg-surface-2 border border-th-border rounded-[12px] p-3">
                    @foreach($categories as $cat)
                        <label class="flex items-center gap-2 text-[12px] text-body bg-surface border border-th-border rounded-[10px] px-3 py-2 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="checkbox" name="categories[]" value="{{ $cat->id }}" @checked($company->categories->contains($cat->id)) class="w-3.5 h-3.5 rounded border-th-border bg-surface text-accent focus:ring-accent" />
                            <span class="truncate">{{ $cat->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="mt-8 pt-6 border-t border-th-border flex items-center justify-end gap-3">
        <a href="{{ route('admin.companies.show', $company->id) }}"
           class="inline-flex items-center justify-center h-11 px-5 rounded-[12px] bg-surface-2 border border-th-border text-[13px] font-semibold text-body hover:text-primary transition-colors">
            {{ __('common.cancel') }}
        </a>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-[12px] bg-accent text-white text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ __('common.save') }}
        </button>
    </div>
</form>

@endsection
