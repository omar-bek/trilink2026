@extends('layouts.app')

@section('title', __('reset.title') . ' — TriLink Trading')

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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                    </svg>
                </div>
                <h1 class="font-display text-[24px] sm:text-[28px] font-bold text-primary leading-tight">{{ __('reset.title') }}</h1>
                <p class="mt-2 text-[13px] sm:text-[14px] text-muted leading-relaxed max-w-[360px]">{{ __('reset.subtitle') }}</p>
            </div>

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

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5"
                  x-data="{ showPassword: false, showConfirm: false, submitting: false }"
                  x-on:submit="submitting = true">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-[12.5px] font-semibold text-primary mb-2">{{ __('auth.email_address') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </span>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                               class="w-full rounded-xl border border-th-border bg-page ps-11 pe-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition" />
                    </div>
                </div>

                {{-- New password --}}
                <div>
                    <label for="password" class="block text-[12.5px] font-semibold text-primary mb-2">{{ __('auth.new_password') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                            </svg>
                        </span>
                        <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password"
                               class="w-full rounded-xl border border-th-border bg-page ps-11 pe-11 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition"
                               placeholder="••••••••" />
                        <button type="button" x-on:click="showPassword = !showPassword"
                                class="absolute inset-y-0 end-0 pe-3.5 flex items-center text-faint hover:text-primary transition-colors focus:outline-none"
                                :aria-label="showPassword ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'">
                            <svg x-show="!showPassword" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showPassword" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11.5px] text-faint">{{ __('reset.password_strength_hint') }}</p>
                </div>

                {{-- Confirm password --}}
                <div>
                    <label for="password_confirmation" class="block text-[12.5px] font-semibold text-primary mb-2">{{ __('auth.confirm_password') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <input id="password_confirmation" name="password_confirmation" type="password" :type="showConfirm ? 'text' : 'password'" required autocomplete="new-password"
                               class="w-full rounded-xl border border-th-border bg-page ps-11 pe-11 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 transition"
                               placeholder="••••••••" />
                        <button type="button" x-on:click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 end-0 pe-3.5 flex items-center text-faint hover:text-primary transition-colors focus:outline-none"
                                :aria-label="showConfirm ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'">
                            <svg x-show="!showConfirm" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showConfirm" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        x-bind:disabled="submitting"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-70 disabled:cursor-wait">
                    <svg x-show="submitting" class="w-[18px] h-[18px] animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('auth.reset_password') }}
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
