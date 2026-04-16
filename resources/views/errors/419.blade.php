@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', '419 — ' . __('errors.page_expired'))

@section('content')

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="relative w-full max-w-xl">
        <div class="absolute inset-0 -z-10 blur-3xl opacity-30 pointer-events-none"
             style="background: radial-gradient(circle at 30% 20%, rgba(255,176,32,0.25), transparent 60%),
                                radial-gradient(circle at 70% 80%, rgba(79,124,255,0.20), transparent 55%);">
        </div>

        <div class="relative bg-surface border border-th-border rounded-3xl p-10 sm:p-12 text-center overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)]">
            <div class="absolute inset-x-0 top-0 h-1"
                 style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 15%, #ffb020 50%, var(--c-border) 85%, transparent 100%);">
            </div>

            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-8" aria-label="TriLink">
                <img src="{{ asset('logo/logo.png') }}" alt="TriLink"
                     class="h-12 w-auto dark:brightness-100 brightness-0 transition-opacity hover:opacity-80" />
            </a>

            <div class="relative w-24 h-24 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full bg-[#ffb020]/10 animate-pulse"></div>
                <div class="absolute inset-2 rounded-full bg-[#ffb020]/15 border border-[#ffb020]/30 flex items-center justify-center">
                    <svg class="w-10 h-10 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-mono font-bold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-[#ffb020] animate-pulse"></span>
                ERROR 419
            </span>

            <h1 class="text-[28px] sm:text-[34px] font-bold text-primary mb-3 leading-tight">
                {{ __('errors.page_expired_title') }}
            </h1>

            <p class="text-[14px] text-muted leading-relaxed mb-2 max-w-md mx-auto">
                {{ __('errors.page_expired_default') }}
            </p>

            <p class="text-[12px] text-faint mb-8 max-w-md mx-auto">
                {{ __('errors.page_expired_hint') }}
            </p>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 mb-6">
                <a href="javascript:location.reload()"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all hover:scale-[1.02]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                    {{ __('errors.refresh_page') }}
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                    {{ __('errors.login_again') }}
                </a>
            </div>

            <div class="pt-6 border-t border-th-border">
                <p class="text-[11px] text-faint">
                    {{ __('errors.page_expired_support') }}
                    <a href="mailto:support@trilink.ae" class="text-accent hover:underline font-semibold">support@trilink.ae</a>
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
