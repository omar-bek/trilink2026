@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', '500 — ' . __('errors.server_error'))

@section('content')

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="relative w-full max-w-xl">
        <div class="absolute inset-0 -z-10 blur-3xl opacity-30 pointer-events-none"
             style="background: radial-gradient(circle at 30% 20%, rgba(239,68,68,0.25), transparent 60%),
                                radial-gradient(circle at 70% 80%, rgba(255,176,32,0.20), transparent 55%);">
        </div>

        <div class="relative bg-surface border border-th-border rounded-3xl p-10 sm:p-12 text-center overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)]">
            <div class="absolute inset-x-0 top-0 h-1"
                 style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 15%, #ef4444 50%, var(--c-border) 85%, transparent 100%);">
            </div>

            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-8" aria-label="TriLink">
                <img src="{{ asset('logo/logo.png') }}" alt="TriLink"
                     class="h-12 w-auto dark:brightness-100 brightness-0 transition-opacity hover:opacity-80" />
            </a>

            <div class="relative w-24 h-24 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full bg-[#ef4444]/10 animate-pulse"></div>
                <div class="absolute inset-2 rounded-full bg-[#ef4444]/15 border border-[#ef4444]/30 flex items-center justify-center">
                    <svg class="w-10 h-10 text-[#ef4444]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384-3.101A2 2 0 005 14v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-1.036-1.931l-5.384-3.101a2 2 0 00-2.16 0zM12 12V3m0 0L8.5 6.5M12 3l3.5 3.5"/>
                    </svg>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-mono font-bold text-[#ef4444] bg-[#ef4444]/10 border border-[#ef4444]/20 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-[#ef4444] animate-pulse"></span>
                ERROR 500
            </span>

            <h1 class="text-[28px] sm:text-[34px] font-bold text-primary mb-3 leading-tight">
                {{ __('errors.server_error_title') }}
            </h1>

            <p class="text-[14px] text-muted leading-relaxed mb-2 max-w-md mx-auto">
                {{ __('errors.server_error_default') }}
            </p>

            <p class="text-[12px] text-faint mb-8 max-w-md mx-auto">
                {{ __('errors.server_error_hint') }}
            </p>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 mb-6">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all hover:scale-[1.02]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12L11.204 3.045a1.125 1.125 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                    {{ __('common.go_home') }}
                </a>
                <a href="javascript:history.back()"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                    {{ __('common.go_back') }}
                </a>
            </div>

            <div class="pt-6 border-t border-th-border">
                <p class="text-[11px] text-faint">
                    {{ __('errors.server_error_support') }}
                    <a href="mailto:support@trilink.ae" class="text-accent hover:underline font-semibold">support@trilink.ae</a>
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
