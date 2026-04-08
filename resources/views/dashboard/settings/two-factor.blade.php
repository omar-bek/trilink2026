@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('two_factor.title'))

@section('content')

<x-dashboard.page-header
    :title="__('two_factor.title')"
    :subtitle="__('two_factor.subtitle')"
    :back="route('dashboard.settings.index')" />

<div class="max-w-3xl space-y-6">

    @if(session('status'))
        <div class="bg-[#00d9b5]/10 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 rounded-xl p-4 text-[13px] text-[#ff4d7f] space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if(! $enabled)
        {{-- Setup mode: QR + first code --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-[#ffb020]/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-[16px] font-bold text-primary">{{ __('two_factor.setup_title') }}</h3>
                    <p class="text-[13px] text-muted mt-1">{{ __('two_factor.setup_intro') }}</p>
                </div>
            </div>

            <ol class="space-y-4 text-[13px] text-body mb-6">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-accent/10 text-accent flex items-center justify-center text-[11px] font-bold flex-shrink-0">1</span>
                    <span>{{ __('two_factor.step_install') }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-accent/10 text-accent flex items-center justify-center text-[11px] font-bold flex-shrink-0">2</span>
                    <span>{{ __('two_factor.step_scan') }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-accent/10 text-accent flex items-center justify-center text-[11px] font-bold flex-shrink-0">3</span>
                    <span>{{ __('two_factor.step_enter') }}</span>
                </li>
            </ol>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-page border border-th-border rounded-xl p-4 text-center">
                    <img src="{{ $qrSrc }}" alt="2FA QR code"
                         class="w-[220px] h-[220px] mx-auto bg-white rounded-lg p-2" />
                    <p class="text-[11px] text-muted mt-3">{{ __('two_factor.scan_hint') }}</p>
                </div>
                <div class="space-y-3">
                    <div>
                        <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('two_factor.manual_key') }}</p>
                        <div class="bg-page border border-th-border rounded-xl p-3 font-mono text-[13px] text-primary break-all select-all">{{ $secret }}</div>
                        <p class="text-[11px] text-muted mt-2">{{ __('two_factor.manual_hint') }}</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('dashboard.two-factor.enable') }}" class="bg-page border border-th-border rounded-xl p-5">
                @csrf
                <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">{{ __('two_factor.verify_code') }}</label>
                <div class="flex items-center gap-3">
                    <input type="text" name="code" required inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           autocomplete="off"
                           class="w-44 bg-surface border border-th-border rounded-lg px-4 py-3 text-[18px] font-mono text-primary tracking-widest text-center"
                           placeholder="000000">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                        {{ __('two_factor.enable_btn') }}
                    </button>
                </div>
            </form>
        </div>
    @else
        {{-- Enabled: show status + disable + recovery codes --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-[#00d9b5]/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-[16px] font-bold text-primary">{{ __('two_factor.enabled_title') }}</h3>
                    <p class="text-[13px] text-muted mt-1">{{ __('two_factor.enabled_subtitle') }}</p>
                </div>
            </div>

            {{-- Recovery codes --}}
            <div class="bg-page border border-th-border rounded-xl p-5 mb-4">
                <p class="text-[11px] font-semibold text-muted uppercase tracking-wide mb-3">{{ __('two_factor.recovery_codes') }}</p>
                <p class="text-[12px] text-muted mb-3">{{ __('two_factor.recovery_hint') }}</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-[13px] text-primary">
                    @foreach($recoveryCodes as $code)
                        <div class="bg-surface border border-th-border rounded-md px-3 py-2">{{ $code }}</div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <form method="POST" action="{{ route('dashboard.two-factor.recovery') }}" class="inline-flex items-center gap-2">
                    @csrf
                    <input type="password" name="password" required placeholder="{{ __('common.password') }}"
                           class="bg-page border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary w-44">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                        {{ __('two_factor.regenerate_recovery') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('dashboard.two-factor.disable') }}" class="inline-flex items-center gap-2">
                    @csrf
                    <input type="password" name="password" required placeholder="{{ __('common.password') }}"
                           class="bg-page border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary w-44">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15">
                        {{ __('two_factor.disable_btn') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

</div>

@endsection
