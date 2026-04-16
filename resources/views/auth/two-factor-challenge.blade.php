@extends('layouts.app')

@section('content')
<div class="min-h-screen grid grid-cols-1 lg:grid-cols-[1fr_minmax(440px,520px)]">

    {{-- Brand side --}}
    <div class="hidden lg:flex flex-col justify-center items-center px-12 relative overflow-hidden"
         style="background: linear-gradient(135deg, #0a0e1a 0%, #111827 50%, #0f172a 100%);">
        <div class="absolute inset-0 pointer-events-none opacity-30"
             style="background: radial-gradient(circle at 25% 25%, rgba(79,124,255,0.15), transparent 50%),
                                radial-gradient(circle at 75% 75%, rgba(0,217,181,0.1), transparent 50%);">
        </div>
        <div class="relative z-10 text-center max-w-md">
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-14 w-auto mx-auto mb-8" />
            <h2 class="text-[28px] font-bold text-white mb-4 leading-tight">{{ __('auth.2fa_brand_title') }}</h2>
            <p class="text-[14px] text-white/60 leading-relaxed">{{ __('auth.2fa_brand_subtitle') }}</p>
        </div>
    </div>

    {{-- Form side --}}
    <div class="flex items-center justify-center px-6 sm:px-10 py-12 bg-page" x-data="{ mode: 'totp', submitting: false }">
        <div class="w-full max-w-[400px]">

            <div class="lg:hidden mb-8 text-center">
                <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-10 w-auto mx-auto mb-4 dark:brightness-100 brightness-0" />
            </div>

            <div class="w-16 h-16 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                </svg>
            </div>

            <h1 class="text-[24px] font-bold text-primary text-center mb-2">{{ __('auth.2fa_title') }}</h1>
            <p class="text-[13px] text-muted text-center mb-8">{{ __('auth.2fa_subtitle') }}</p>

            @if($errors->any())
            <div class="mb-6 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/10 px-4 py-3 text-[13px] text-[#ff4d7f]">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
            @endif

            {{-- Toggle --}}
            <div class="flex items-center gap-2 mb-6 bg-surface border border-th-border rounded-xl p-1">
                <button type="button" @click="mode = 'totp'" :class="mode === 'totp' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-primary'" class="flex-1 h-10 rounded-lg text-[13px] font-semibold transition-all">
                    {{ __('auth.2fa_authenticator') }}
                </button>
                <button type="button" @click="mode = 'recovery'" :class="mode === 'recovery' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-primary'" class="flex-1 h-10 rounded-lg text-[13px] font-semibold transition-all">
                    {{ __('auth.2fa_recovery') }}
                </button>
            </div>

            {{-- TOTP form --}}
            <form method="POST" action="{{ route('two-factor.challenge') }}" x-show="mode === 'totp'" @submit="submitting = true">
                @csrf
                <div class="mb-6">
                    <label class="block text-[12px] font-semibold text-muted mb-2">{{ __('auth.2fa_code_label') }}</label>
                    <input name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus autocomplete="one-time-code"
                           class="w-full bg-surface border border-th-border rounded-xl px-4 h-14 text-[24px] font-mono text-primary text-center tracking-[0.5em] placeholder:text-muted/30 focus:border-accent/50 focus:ring-2 focus:ring-accent/15"
                           placeholder="000000" />
                </div>
                <button type="submit" :disabled="submitting"
                        class="w-full h-12 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                    <template x-if="submitting"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    {{ __('auth.2fa_verify') }}
                </button>
            </form>

            {{-- Recovery form --}}
            <form method="POST" action="{{ route('two-factor.challenge') }}" x-show="mode === 'recovery'" x-cloak @submit="submitting = true">
                @csrf
                <div class="mb-6">
                    <label class="block text-[12px] font-semibold text-muted mb-2">{{ __('auth.2fa_recovery_label') }}</label>
                    <input name="recovery_code" type="text" autocomplete="off"
                           class="w-full bg-surface border border-th-border rounded-xl px-4 h-14 text-[16px] font-mono text-primary text-center tracking-wider placeholder:text-muted/30 focus:border-accent/50 focus:ring-2 focus:ring-accent/15"
                           placeholder="xxxx-xxxx-xxxx" />
                </div>
                <button type="submit" :disabled="submitting"
                        class="w-full h-12 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                    <template x-if="submitting"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    {{ __('auth.2fa_verify') }}
                </button>
            </form>

            <p class="text-[12px] text-muted text-center mt-6">
                <a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('auth.2fa_back_to_login') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
