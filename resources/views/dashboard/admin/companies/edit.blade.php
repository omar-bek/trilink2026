@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.companies.edit'))

@section('content')

<x-dashboard.page-header :title="__('admin.companies.edit')" :subtitle="$company->name" :back="route('admin.companies.show', $company->id)" />

@include('dashboard.admin._tabs', ['active' => 'companies'])

<form method="POST" action="{{ route('admin.companies.update', $company->id) }}" class="bg-surface border border-th-border rounded-2xl p-6 max-w-4xl">
    @csrf @method('PATCH')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.name') }} *</label>
            <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.name_ar') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $company->name_ar) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.registration_number') }} *</label>
            <input type="text" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" required
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.tax_number') }}</label>
            <input type="text" name="tax_number" value="{{ old('tax_number', $company->tax_number) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.type') }} *</label>
            <select name="type" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                @foreach(\App\Enums\CompanyType::cases() as $t)
                    <option value="{{ $t->value }}" @selected(old('type', $company->type?->value) === $t->value)>{{ __('role.' . $t->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('common.status') }} *</label>
            <select name="status" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                @foreach(\App\Enums\CompanyStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $company->status?->value) === $s->value)>{{ __('status.' . $s->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.email') }}</label>
            <input type="email" name="email" value="{{ old('email', $company->email) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $company->phone) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.website') }}</label>
            <input type="url" name="website" value="{{ old('website', $company->website) }}" placeholder="https://"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.city') }}</label>
            <input type="text" name="city" value="{{ old('city', $company->city) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div class="md:col-span-2">
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.country') }}</label>
            <input type="text" name="country" value="{{ old('country', $company->country) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div class="md:col-span-2">
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.address') }}</label>
            <textarea name="address" rows="2" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">{{ old('address', $company->address) }}</textarea>
        </div>
        <div class="md:col-span-2">
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.description') }}</label>
            <textarea name="description" rows="4" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">{{ old('description', $company->description) }}</textarea>
        </div>
        @if($categories->isNotEmpty())
        <div class="md:col-span-2">
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.companies.categories') }}</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto bg-surface-2 border border-th-border rounded-lg p-3">
                @foreach($categories as $cat)
                    <label class="flex items-center gap-2 text-[12px] text-body">
                        <input type="checkbox" name="categories[]" value="{{ $cat->id }}" @checked($company->categories->contains($cat->id)) />
                        {{ $cat->name }}
                    </label>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="bg-accent text-white px-5 py-2.5 rounded-lg text-[13px] font-semibold">{{ __('common.save') }}</button>
        <a href="{{ route('admin.companies.show', $company->id) }}" class="text-[13px] text-muted hover:text-primary">{{ __('common.cancel') }}</a>
    </div>
</form>

@endsection
