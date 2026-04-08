@extends('layouts.app')

@section('title', __('login.page_title') . ' — TriLink Trading')

@section('content')

<x-landing.navbar />

{{-- =====================================================================
     Auth shell — split layout. Brand panel on the start side (lg+),
     form panel on the end side. Stacks vertically on mobile so the
     form is the first thing visible without scrolling.
     ===================================================================== --}}
<main class="relative overflow-hidden bg-page min-h-screen pt-24 sm:pt-28 lg:pt-32 pb-12 sm:pb-16">
    {{-- Soft radial spotlight + mesh — purely decorative, never blocks input. --}}
    <div class="pointer-events-none absolute inset-0 bg-spotlight opacity-60" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 bg-mesh opacity-40" aria-hidden="true"></div>

    <div class="relative mx-auto w-full max-w-[1200px] px-4 sm:px-6 lg:px-10">
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_minmax(440px,520px)] gap-8 lg:gap-14 items-center">

            {{-- =====================================================
                 BRAND PANEL — hidden on mobile, full pitch on lg+
                 ===================================================== --}}
            <aside class="hidden lg:flex flex-col gap-10 ps-2">
                <div class="space-y-5">
                    <span class="t-eyebrow">{{ __('login.brand_eyebrow') }}</span>
                    <h2 class="font-display text-[40px] xl:text-[44px] leading-[1.1] tracking-[-0.02em] font-bold text-primary">
                        {{ __('login.brand_headline') }}
                    </h2>
                    <p class="text-[15px] leading-relaxed text-muted max-w-[460px]">
                        {{ __('login.brand_subtitle') }}
                    </p>
                </div>

                {{-- Feature list with icons --}}
                <ul class="space-y-5">
                    @php
                        $brandFeatures = [
                            [
                                'title' => __('login.brand_feat_rfq_title'),
                                'body'  => __('login.brand_feat_rfq_body'),
                                'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                            ],
                            [
                                'title' => __('login.brand_feat_escrow_title'),
                                'body'  => __('login.brand_feat_escrow_body'),
                                'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            ],
                            [
                                'title' => __('login.brand_feat_track_title'),
                                'body'  => __('login.brand_feat_track_body'),
                                'icon'  => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m-6 3l6-3',
                            ],
                        ];
                    @endphp
                    @foreach($brandFeatures as $feat)
                        <li class="flex items-start gap-4">
                            <span class="flex-shrink-0 w-11 h-11 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feat['icon'] }}"/>
                                </svg>
                            </span>
                            <div class="space-y-1">
                                <h3 class="text-[15px] font-semibold text-primary">{{ $feat['title'] }}</h3>
                                <p class="text-[13px] leading-relaxed text-muted max-w-[380px]">{{ $feat['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Trust badges --}}
                <div class="flex items-center gap-3 pt-2">
                    <div class="flex -space-x-2 rtl:space-x-reverse rtl:-space-x-reverse">
                        @foreach(['#4f7cff','#00d9b5','#8B5CF6','#ffb020'] as $color)
                            <span class="w-8 h-8 rounded-full border-2 border-page" style="background:{{ $color }}"></span>
                        @endforeach
                    </div>
                    <p class="text-[12px] text-faint leading-snug">
                        {{ __('common.trusted_by') ?? 'Trusted by procurement teams across the UAE, KSA and the wider GCC.' }}
                    </p>
                </div>
            </aside>

            {{-- =====================================================
                 FORM PANEL
                 ===================================================== --}}
            <section class="w-full">
                <div class="bg-surface border border-th-border rounded-3xl shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)] p-7 sm:p-10">

                    {{-- Mobile-only brand header (lg+ uses the side panel) --}}
                    <div class="lg:hidden mb-7 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </span>
                        <span class="text-[13px] font-semibold tracking-wide text-muted uppercase">{{ __('app.name') }}</span>
                    </div>

                    {{-- Heading --}}
                    <header class="mb-7">
                        <h1 class="font-display text-[28px] sm:text-[32px] font-bold text-primary leading-tight">
                            {{ __('login.title') }}
                        </h1>
                        <p class="mt-2 text-[14px] sm:text-[15px] text-muted leading-relaxed">
                            {{ __('login.subtitle') }}
                        </p>
                    </header>

                    {{-- Errors --}}
                    @if ($errors->any())
                        <div role="alert" class="mb-6 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/10 p-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-[#ff4d7f] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-semibold text-[#FCA5A5] mb-1">{{ __('login.errors_title') }}</p>
                                    <ul class="text-[12.5px] text-[#FCA5A5]/90 space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Form --}}
                    <form method="POST"
                          action="{{ route('login.attempt') }}"
                          class="space-y-5"
                          x-data="{ showPassword: false, submitting: false }"
                          x-on:submit="submitting = true">
                        @csrf

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-[12.5px] font-semibold text-primary mb-2">
                                {{ __('auth.email_address') }}
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                    </svg>
                                </span>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    inputmode="email"
                                    class="w-full rounded-xl border border-th-border bg-page ps-11 pe-4 py-3 text-[14px] text-primary placeholder:text-faint transition focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 @error('email') border-[#ff4d7f]/60 @enderror"
                                    placeholder="{{ __('auth.email_placeholder') }}"
                                    aria-invalid="@error('email') true @else false @enderror"
                                />
                            </div>
                        </div>

                        {{-- Password --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="text-[12.5px] font-semibold text-primary">
                                    {{ __('login.password_label') }}
                                </label>
                                <a href="{{ route('password.request') }}" class="text-[12px] font-medium text-accent hover:text-accent-h transition-colors">
                                    {{ __('auth.forgot_password') }}
                                </a>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 start-0 ps-3.5 flex items-center text-faint pointer-events-none">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                    </svg>
                                </span>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    class="w-full rounded-xl border border-th-border bg-page ps-11 pe-11 py-3 text-[14px] text-primary placeholder:text-faint transition focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25 @error('password') border-[#ff4d7f]/60 @enderror"
                                    placeholder="{{ __('login.password_placeholder') }}"
                                    aria-invalid="@error('password') true @else false @enderror"
                                />
                                <button type="button"
                                        x-on:click="showPassword = !showPassword"
                                        class="absolute inset-y-0 end-0 pe-3.5 flex items-center text-faint hover:text-primary transition-colors focus:outline-none focus-visible:text-accent"
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
                        </div>

                        {{-- Remember + meta row --}}
                        <label class="flex items-center gap-2.5 text-[13px] text-muted cursor-pointer select-none">
                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                                class="h-4 w-4 rounded border-th-border bg-page text-accent focus:ring-2 focus:ring-accent/40 focus:ring-offset-0 transition"
                            />
                            {{ __('login.remember') }}
                        </label>

                        {{-- Submit --}}
                        <button
                            type="submit"
                            x-bind:disabled="submitting"
                            class="group w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-70 disabled:cursor-wait"
                        >
                            <svg x-show="submitting" class="w-[18px] h-[18px] animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true" x-cloak>
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-show="!submitting">{{ __('login.submit') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('login.signing_in') }}</span>
                            <svg x-show="!submitting" class="w-[16px] h-[16px] transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </button>
                    </form>

                    {{-- Demo accounts — collapsible card --}}
                    <div x-data="{ open: false }" class="mt-6 rounded-xl border border-th-border bg-surface-2/60 overflow-hidden">
                        <button type="button"
                                x-on:click="open = !open"
                                class="w-full flex items-center justify-between gap-3 px-4 py-3 text-start hover:bg-surface-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/30">
                            <span class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-[#00d9b5]/10 border border-[#00d9b5]/25 flex items-center justify-center text-[#00d9b5]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.091z"/>
                                    </svg>
                                </span>
                                <span class="text-[13px] font-semibold text-primary">{{ __('login.demo_title') }}</span>
                            </span>
                            <svg class="w-4 h-4 text-muted transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-collapse class="border-t border-th-border">
                            <p class="px-4 pt-3 text-[12px] text-muted leading-relaxed">{!! __('login.demo_subtitle') !!}</p>
                            <ul class="px-2 pt-2 pb-3 space-y-1">
                                @php
                                    $demoAccounts = [
                                        ['email' => 'buyer@al-ahram.test',     'label' => __('login.demo_buyer'),    'desc' => __('login.demo_buyer_desc'),    'color' => '#4f7cff'],
                                        ['email' => 'mohammed@emirates-ind.test','label' => __('login.demo_supplier'),'desc' => __('login.demo_supplier_desc'),'color' => '#00d9b5'],
                                        ['email' => 'admin@trilink.test',      'label' => __('login.demo_admin'),    'desc' => __('login.demo_admin_desc'),    'color' => '#ff4d7f'],
                                    ];
                                @endphp
                                @foreach($demoAccounts as $acct)
                                    <li>
                                        <button type="button"
                                                x-on:click="document.getElementById('email').value = '{{ $acct['email'] }}'; document.getElementById('password').value = 'password'; document.getElementById('email').focus();"
                                                class="w-full flex items-start gap-3 px-3 py-2.5 rounded-lg text-start hover:bg-surface transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/30">
                                            <span class="flex-shrink-0 w-2 h-2 rounded-full mt-2" style="background:{{ $acct['color'] }}"></span>
                                            <span class="flex-1 min-w-0">
                                                <span class="flex items-center gap-2 mb-0.5">
                                                    <span class="text-[12.5px] font-semibold text-primary">{{ $acct['label'] }}</span>
                                                    <code class="font-mono text-[11px] text-muted truncate">{{ $acct['email'] }}</code>
                                                </span>
                                                <span class="block text-[11.5px] text-faint leading-snug">{{ $acct['desc'] }}</span>
                                            </span>
                                            <span class="text-[11px] font-semibold text-accent flex-shrink-0 mt-0.5">{{ __('login.demo_use_label') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- Footer link --}}
                    <p class="mt-6 text-center text-[13px] text-muted">
                        {{ __('login.no_account') }}
                        <a href="{{ route('register') }}" class="font-semibold text-accent hover:text-accent-h transition-colors">
                            {{ __('login.create_account') }}
                        </a>
                    </p>
                </div>
            </section>
        </div>
    </div>
</main>

<x-landing.footer />

@endsection
