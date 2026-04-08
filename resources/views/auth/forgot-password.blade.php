@extends('layouts.app')

@section('title', __('forgot.title') . ' — TriLink Trading')

@section('content')

<x-landing.navbar />

<main class="relative overflow-hidden bg-page min-h-screen pt-24 sm:pt-28 lg:pt-32 pb-12 sm:pb-20">
    <div class="pointer-events-none absolute inset-0 bg-spotlight opacity-50" aria-hidden="true"></div>

    <div class="relative mx-auto w-full max-w-[480px] px-4 sm:px-6">
        <div class="bg-surface border border-th-border rounded-3xl shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)] p-7 sm:p-10">

            {{-- Icon header --}}
            <div class="flex flex-col items-center text-center mb-7">
                <div class="w-14 h-14 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent mb-5">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
                <h1 class="font-display text-[24px] sm:text-[28px] font-bold text-primary leading-tight">{{ __('forgot.title') }}</h1>
                <p class="mt-2 text-[13px] sm:text-[14px] text-muted leading-relaxed max-w-[360px]">{{ __('forgot.subtitle') }}</p>
            </div>

            {{-- Status / errors --}}
            @if(session('status'))
                <div role="status" class="mb-5 rounded-xl border border-[#00d9b5]/30 bg-[#00d9b5]/10 p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#00d9b5] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="flex-1 text-[13px] text-[#00d9b5]">{{ session('status') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/10 p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <ul class="flex-1 text-[12.5px] text-[#FCA5A5] space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf

                <div>
                    <label for="email" class="block text-[12.5px] font-semibold text-primary mb-2">{{ __('auth.email_address') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </span>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            inputmode="email"
                            class="w-full rounded-xl border border-th-border bg-page ps-11 pe-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition"
                            placeholder="{{ __('auth.email_placeholder') }}"
                        />
                    </div>
                    <p class="mt-1.5 text-[11.5px] text-faint">{{ __('forgot.email_help') }}</p>
                </div>

                <button
                    type="submit"
                    x-bind:disabled="submitting"
                    class="group w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-70 disabled:cursor-wait"
                >
                    <svg x-show="submitting" class="w-[18px] h-[18px] animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg x-show="!submitting" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                    <span>{{ __('auth.send_reset_link') }}</span>
                </button>
            </form>

            <p class="mt-6 text-center text-[13px] text-muted">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 font-semibold text-accent hover:text-accent-h transition-colors">
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    {{ __('auth.back_to_login') }}
                </a>
            </p>
        </div>
    </div>
</main>

<x-landing.footer />

@endsection
