@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', '403 — ' . __('errors.forbidden'))

@section('content')

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="relative w-full max-w-xl">

        {{-- Decorative gradient glow behind the card --}}
        <div class="absolute inset-0 -z-10 blur-3xl opacity-30 pointer-events-none"
             style="background: radial-gradient(circle at 30% 20%, rgba(239,68,68,0.25), transparent 60%),
                                radial-gradient(circle at 70% 80%, rgba(79,124,255,0.20), transparent 55%);">
        </div>

        <div class="relative bg-surface border border-th-border rounded-3xl p-10 sm:p-12 text-center overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)]">

            {{-- Top accent bar --}}
            <div class="absolute inset-x-0 top-0 h-1"
                 style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 15%, #ff4d7f 50%, var(--c-border) 85%, transparent 100%);">
            </div>

            {{-- Brand logo --}}
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center mb-8" aria-label="TriLink">
                <img src="{{ asset('logo/logo.png') }}" alt="TriLink"
                     class="h-12 w-auto dark:brightness-100 brightness-0 transition-opacity hover:opacity-80" />
            </a>

            {{-- Lock icon ring --}}
            <div class="relative w-24 h-24 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full bg-[#ff4d7f]/10 animate-pulse"></div>
                <div class="absolute inset-2 rounded-full bg-[#ff4d7f]/15 border border-[#ff4d7f]/30 flex items-center justify-center">
                    <svg class="w-10 h-10 text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
            </div>

            {{-- Status code chip --}}
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-mono font-bold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-[#ff4d7f] animate-pulse"></span>
                ERROR 403
            </span>

            {{-- Heading --}}
            <h1 class="text-[28px] sm:text-[34px] font-bold text-primary mb-3 leading-tight">
                {{ __('errors.forbidden_title') }}
            </h1>

            {{-- Message — `$exception` is provided by Laravel's error view; fall
                 back to the default copy if it's not bound or has no message. --}}
            @php
                $errorMessage = (isset($exception) && $exception?->getMessage())
                    ? $exception->getMessage()
                    : (isset($message) && $message ? $message : __('errors.forbidden_default'));
            @endphp
            <p class="text-[14px] text-muted leading-relaxed mb-2 max-w-md mx-auto">
                {{ $errorMessage }}
            </p>

            {{-- Helpful hint --}}
            <p class="text-[12px] text-faint mb-8 max-w-md mx-auto">
                {{ __('errors.forbidden_hint') }}
            </p>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 mb-6">
                <a href="{{ route('dashboard') }}"
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

            {{-- Support footer --}}
            <div class="pt-6 border-t border-th-border">
                <p class="text-[11px] text-faint">
                    {{ __('errors.forbidden_support') }}
                    <a href="mailto:support@trilink.ae" class="text-accent hover:underline font-semibold">support@trilink.ae</a>
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
