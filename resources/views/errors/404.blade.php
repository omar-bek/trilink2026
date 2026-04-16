@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', '404 — ' . __('errors.not_found'))

@section('content')

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="relative w-full max-w-xl">
        <div class="absolute inset-0 -z-10 blur-3xl opacity-30 pointer-events-none"
             style="background: radial-gradient(circle at 30% 20%, rgba(79,124,255,0.25), transparent 60%),
                                radial-gradient(circle at 70% 80%, rgba(0,217,181,0.20), transparent 55%);">
        </div>

        <div class="relative bg-surface border border-th-border rounded-3xl p-10 sm:p-12 text-center overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)]">
            <div class="absolute inset-x-0 top-0 h-1"
                 style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 15%, #4f7cff 50%, var(--c-border) 85%, transparent 100%);">
            </div>

            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-8" aria-label="TriLink">
                <img src="{{ asset('logo/logo.png') }}" alt="TriLink"
                     class="h-12 w-auto dark:brightness-100 brightness-0 transition-opacity hover:opacity-80" />
            </a>

            <div class="relative w-24 h-24 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full bg-accent/10 animate-pulse"></div>
                <div class="absolute inset-2 rounded-full bg-accent/15 border border-accent/30 flex items-center justify-center">
                    <svg class="w-10 h-10 text-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-mono font-bold text-accent bg-accent/10 border border-accent/20 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                ERROR 404
            </span>

            <h1 class="text-[28px] sm:text-[34px] font-bold text-primary mb-3 leading-tight">
                {{ __('errors.not_found_title') }}
            </h1>

            <p class="text-[14px] text-muted leading-relaxed mb-2 max-w-md mx-auto">
                {{ __('errors.not_found_default') }}
            </p>

            <p class="text-[12px] text-faint mb-8 max-w-md mx-auto">
                {{ __('errors.not_found_hint') }}
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
                    {{ __('errors.not_found_support') }}
                    <a href="mailto:support@trilink.ae" class="text-accent hover:underline font-semibold">support@trilink.ae</a>
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
