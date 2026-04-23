@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.branding_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.branding_title')" :subtitle="__('settings.branding_subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PATCH')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.invoice_logo') }}</label>
                @if($branding->invoice_logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($branding->invoice_logo_path) }}" class="h-14 mb-2 object-contain">
                @endif
                <input type="file" name="invoice_logo" accept="image/*" class="text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.email_logo') }}</label>
                @if($branding->email_logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($branding->email_logo_path) }}" class="h-14 mb-2 object-contain">
                @endif
                <input type="file" name="email_logo" accept="image/*" class="text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.primary_color') }}</label>
                <input type="color" name="primary_color" value="{{ old('primary_color', $branding->primary_color ?: '#4f7cff') }}" class="h-10 w-20">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.accent_color') }}</label>
                <input type="color" name="accent_color" value="{{ old('accent_color', $branding->accent_color ?: '#00d9b5') }}" class="h-10 w-20">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.email_from_name') }}</label>
                <input type="text" name="email_from_name" value="{{ old('email_from_name', $branding->email_from_name) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.email_from_address') }}</label>
                <input type="email" name="email_from_address" value="{{ old('email_from_address', $branding->email_from_address) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
                @if($branding->email_from_address && ! $branding->email_sender_verified)
                    <p class="text-[12px] text-[#ffc24d] mt-1">{{ __('settings.email_sender_unverified') }}</p>
                @endif
            </div>
        </div>

        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.invoice_footer') }}</label>
            <textarea name="invoice_footer_text" rows="3" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">{{ old('invoice_footer_text', $branding->invoice_footer_text) }}</textarea>
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.contract_footer') }}</label>
            <textarea name="contract_footer_text" rows="3" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">{{ old('contract_footer_text', $branding->contract_footer_text) }}</textarea>
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.po_footer') }}</label>
            <textarea name="po_footer_text" rows="3" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">{{ old('po_footer_text', $branding->po_footer_text) }}</textarea>
        </div>

        <div class="pt-4 border-t border-th-border">
            <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('settings.save') }}</button>
        </div>
    </form>
</div>
@endsection
