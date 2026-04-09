@extends('layouts.dashboard', ['active' => 'icv-certificates'])
@section('title', __('icv.upload_certificate'))

@section('content')

<a href="{{ route('dashboard.icv-certificates.index') }}" class="inline-flex items-center gap-2 text-[13px] text-muted hover:text-primary mb-4 transition-colors">
    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
    {{ __('icv.back_to_list') }}
</a>

<div class="mb-8">
    <h1 class="text-[28px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('icv.upload_certificate') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('icv.upload_subtitle') }}</p>
</div>

@if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[13px] text-[#ff4d7f]">
        @foreach($errors->all() as $err)
            <p>{{ $err }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('dashboard.icv-certificates.store') }}" enctype="multipart/form-data" class="max-w-2xl">
    @csrf

    <div class="bg-surface border border-th-border rounded-2xl p-6 space-y-5">

        <div>
            <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_issuer') }} *</label>
            <select name="issuer" required class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/60">
                <option value="">{{ __('icv.field_issuer_placeholder') }}</option>
                @foreach($issuers as $issuer)
                    <option value="{{ $issuer }}" @selected(old('issuer') === $issuer)>{{ __('icv.issuer_' . $issuer) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_certificate_number') }} *</label>
            <input type="text" name="certificate_number" required maxlength="64" value="{{ old('certificate_number') }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary font-mono focus:outline-none focus:border-accent/60"
                   placeholder="e.g. ICV-2026-12345">
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_score') }} *</label>
            <div class="relative">
                <input type="number" name="score" required min="0" max="100" step="0.01" value="{{ old('score') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/60"
                       placeholder="38.45">
                <span class="absolute end-4 top-1/2 -translate-y-1/2 text-[14px] text-muted">%</span>
            </div>
            <p class="text-[11px] text-muted mt-1">{{ __('icv.field_score_help') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_issued_date') }} *</label>
                <input type="date" name="issued_date" required value="{{ old('issued_date') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/60">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_expires_date') }} *</label>
                <input type="date" name="expires_date" required value="{{ old('expires_date') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/60">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-muted uppercase tracking-wider mb-2">{{ __('icv.field_file') }} *</label>
            <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary file:bg-accent file:text-white file:rounded-lg file:px-3 file:py-1 file:border-0 file:me-3 focus:outline-none focus:border-accent/60">
            <p class="text-[11px] text-muted mt-1">{{ __('icv.field_file_help') }}</p>
        </div>

        <div class="pt-4 border-t border-th-border flex items-center justify-end gap-3">
            <a href="{{ route('dashboard.icv-certificates.index') }}" class="px-5 py-2.5 rounded-xl text-[13px] font-medium text-muted hover:text-primary">{{ __('common.cancel') }}</a>
            <button type="submit" class="inline-flex items-center gap-2 px-5 h-11 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                {{ __('icv.submit_for_review') }}
            </button>
        </div>
    </div>
</form>

@endsection
