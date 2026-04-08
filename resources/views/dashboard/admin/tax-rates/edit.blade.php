@extends('layouts.dashboard', ['active' => 'admin-tax-rates'])
@section('title', __('admin.tax_rates.edit'))

@section('content')

<x-dashboard.page-header :title="__('admin.tax_rates.edit')" :subtitle="$taxRate->name" :back="route('admin.tax-rates.index')" />

<x-admin.navbar active="tax-rates" />

<form method="POST" action="{{ route('admin.tax-rates.update', $taxRate->id) }}" class="space-y-5">
    @csrf
    @method('PATCH')
    @include('dashboard.admin.tax-rates._form', ['taxRate' => $taxRate, 'categories' => $categories])

    <div class="max-w-4xl flex items-center justify-end gap-3">
        <a href="{{ route('admin.tax-rates.index') }}"
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
