@extends('layouts.dashboard', ['active' => 'documents'])
@section('title', __('trust.vault_title'))

@section('content')

<x-dashboard.page-header :title="__('trust.vault_title')" :subtitle="__('trust.vault_subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-4 flex items-center gap-3 flex-wrap">
    <x-dashboard.verification-badge :level="auth()->user()->company?->verification_level" />
</div>

{{-- Compliance summary strip — Valid / Expiring Soon / Expired counts so
     the manager can see at a glance whether their vault is healthy. The
     same numbers feed the Documents Dashboard described in Phase 0 / 0.7. --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <x-dashboard.stat-card :value="$stats['total']"    :label="__('trust.stat_total')"    color="slate" />
    <x-dashboard.stat-card :value="$stats['verified']" :label="__('trust.stat_verified')" color="green" />
    <x-dashboard.stat-card :value="$stats['expiring']" :label="__('trust.stat_expiring')" color="orange" />
    <x-dashboard.stat-card :value="$stats['expired']"  :label="__('trust.stat_expired')"  color="red" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-3">
        @forelse($documents as $doc)
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-[14px] font-bold text-primary">{{ __('trust.doc_' . $doc->type->value) }}</span>
                        @if($doc->status === \App\Models\CompanyDocument::STATUS_VERIFIED)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('trust.status_verified') }}</span>
                        @elseif($doc->status === \App\Models\CompanyDocument::STATUS_PENDING)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ __('trust.status_pending') }}</span>
                        @elseif($doc->status === \App\Models\CompanyDocument::STATUS_REJECTED)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#ff4d7f]/10 text-[#ff4d7f] border border-[#ff4d7f]/20">{{ __('trust.status_rejected') }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">{{ __('trust.status_expired') }}</span>
                        @endif

                        {{-- Expiry pill (Phase 0 / task 0.7) — independent of
                             approval status. Driven purely by expires_at. --}}
                        @if($doc->status === \App\Models\CompanyDocument::STATUS_EXPIRED || $doc->isExpired())
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400 border border-red-500/20">{{ __('trust.expiry_expired') }}</span>
                        @elseif($doc->isExpiringSoon())
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ __('trust.expiry_soon') }}</span>
                        @elseif($doc->expires_at)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('trust.expiry_valid') }}</span>
                        @endif
                    </div>
                    <p class="text-[12px] text-muted mb-2">{{ $doc->original_filename ?? '—' }}</p>
                    <div class="flex items-center gap-4 text-[11px] text-muted">
                        @if($doc->expires_at)
                            <span>{{ __('trust.expires_on') }}: <span class="text-primary font-semibold">{{ $doc->expires_at->format('M j, Y') }}</span></span>
                        @endif
                        @if($doc->uploadedBy)
                            <span>{{ __('trust.uploaded_by') }}: {{ trim(($doc->uploadedBy->first_name ?? '') . ' ' . ($doc->uploadedBy->last_name ?? '')) }}</span>
                        @endif
                    </div>
                    @if($doc->status === \App\Models\CompanyDocument::STATUS_REJECTED && $doc->rejection_reason)
                        <p class="mt-2 text-[12px] text-[#ff4d7f]">{{ __('trust.reason') }}: {{ $doc->rejection_reason }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a href="{{ route('dashboard.documents.download', $doc->id) }}"
                       class="text-accent hover:underline text-[12px] font-semibold">{{ __('common.download') }}</a>
                    <form method="POST" action="{{ route('dashboard.documents.destroy', $doc->id) }}" onsubmit="return confirm('{{ __('trust.confirm_delete') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold">{{ __('common.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </div>
            <p class="text-[14px] font-bold text-primary">{{ __('trust.no_documents') }}</p>
            <p class="text-[12px] text-muted mt-1">{{ __('trust.no_documents_hint') ?? __('trust.upload_new') }}</p>
        </div>
        @endforelse
    </div>

    <div>
        <div class="bg-surface border border-th-border rounded-2xl p-6 sticky top-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('trust.upload_new') }}</h3>
            <form method="POST" action="{{ route('dashboard.documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('trust.document_type') }}</label>
                    <select name="type" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                        @foreach($types as $t)
                            <option value="{{ $t->value }}">{{ __('trust.doc_' . $t->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('trust.label_optional') }}</label>
                    <input type="text" name="label" maxlength="191" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('trust.file') }}</label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[12px] text-primary" />
                    <p class="text-[10px] text-muted mt-1">{{ __('trust.file_hint') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('trust.issued_at') }}</label>
                        <input type="date" name="issued_at" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('trust.expires_at') }}</label>
                        <input type="date" name="expires_at" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    {{ __('trust.upload') }}
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
