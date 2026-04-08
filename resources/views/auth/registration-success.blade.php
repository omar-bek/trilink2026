@extends('layouts.app')

@section('title', __('success.page_title') . ' — TriLink Trading')

@section('content')

@php $infoRequest = $infoRequest ?? null; @endphp

<x-landing.navbar />

<main class="relative overflow-hidden bg-page min-h-screen pt-24 sm:pt-28 lg:pt-32 pb-12 sm:pb-20">
    <div class="pointer-events-none absolute inset-0 bg-spotlight opacity-50" aria-hidden="true"></div>

    <div class="relative mx-auto w-full max-w-[680px] px-4 sm:px-6">

        @if(session('status'))
            <div role="status" class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px] flex items-start gap-3">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Success icon with glow --}}
        <div class="flex justify-center mb-7 sm:mb-8">
            <div class="relative">
                <div class="absolute inset-0 blur-[40px] bg-[#22C55E]/20 rounded-full animate-pulse" aria-hidden="true"></div>
                <div class="relative w-[88px] h-[88px] sm:w-[96px] sm:h-[96px] rounded-full bg-[#22C55E]/10 border border-[#22C55E]/30 flex items-center justify-center">
                    <svg class="w-12 h-12 sm:w-14 sm:h-14 text-[#22C55E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Main card --}}
        <div class="bg-surface border border-th-border rounded-3xl shadow-[0_24px_60px_-20px_rgba(0,0,0,0.35)] p-6 sm:p-10">
            {{-- Heading --}}
            <div class="text-center mb-7 sm:mb-8">
                <h1 class="font-display text-[24px] sm:text-[30px] font-bold text-primary mb-3 leading-tight">{{ __('success.title') }}</h1>
                <p class="text-[14px] text-muted leading-relaxed max-w-[480px] mx-auto">{{ __('success.subtitle') }}</p>
                @auth
                    <p class="mt-3 text-[12px] text-muted">
                        {{ __('success.signed_in_as') }} <span class="text-primary font-semibold">{{ auth()->user()->email }}</span>
                    </p>
                @endauth
            </div>

            {{-- Pending approval banner --}}
            <div class="bg-[#ffb020]/5 border border-[#ffb020]/20 rounded-xl px-5 sm:px-6 py-5 text-center mb-7 sm:mb-8">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-[15px] sm:text-[16px] font-bold text-primary">{{ __('success.pending_title') }}</h3>
                </div>
                <p class="text-[12.5px] sm:text-[13px] text-muted">{{ __('success.pending_subtitle') }}</p>
            </div>

            {{-- Admin requested additional info — show a tailored form. --}}
            @if($infoRequest)
            <div class="bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-2xl p-5 sm:p-6 mb-7 sm:mb-8">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-[#ff4d7f]/15 border border-[#ff4d7f]/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-[15px] sm:text-[16px] font-bold text-primary leading-tight">{{ __('register.info_required_title') }}</h3>
                        <p class="text-[12.5px] sm:text-[13px] text-muted mt-0.5">{{ __('register.info_required_subtitle') }}</p>
                    </div>
                </div>

                @if(!empty($infoRequest['note']))
                <div class="bg-surface-2 border border-th-border rounded-xl p-4 mb-5">
                    <p class="text-[10px] uppercase font-bold text-faint tracking-wider mb-1">{{ __('register.admin_note') }}</p>
                    <p class="text-[13px] text-body whitespace-pre-line">{{ $infoRequest['note'] }}</p>
                </div>
                @endif

                <form method="POST" action="{{ route('register.submit-info') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @foreach($infoRequest['items'] as $item)
                    @php
                        $key   = $item['key'];
                        $label = __($item['label_key']);
                        $kind  = $item['kind'];
                        $type  = $item['input_type'] ?? 'text';
                        $err   = $errors->first($key);
                    @endphp
                    <div>
                        <label for="info-{{ $key }}" class="block text-[12.5px] font-semibold text-primary mb-2">
                            {{ $label }} <span class="text-red-500">*</span>
                        </label>

                        @if($kind === 'document')
                            <input type="file" id="info-{{ $key }}" name="{{ $key }}" required
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full bg-page border {{ $err ? 'border-red-500/60' : 'border-th-border' }} rounded-lg px-3 py-2.5 text-[13px] text-primary file:me-3 file:bg-accent file:text-white file:border-0 file:px-3 file:py-1 file:rounded file:text-[12px] file:font-semibold file:cursor-pointer" />
                            <p class="text-[11px] text-faint mt-1">{{ __('success.upload_hint') }}</p>
                        @elseif($type === 'textarea')
                            <textarea id="info-{{ $key }}" name="{{ $key }}" rows="3" required
                                      class="w-full bg-page border {{ $err ? 'border-red-500/60' : 'border-th-border' }} rounded-lg px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent resize-none">{{ old($key) }}</textarea>
                        @else
                            <input type="{{ $type }}" id="info-{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" required
                                   @if($key === 'name_ar') dir="rtl" @endif
                                   class="w-full bg-page border {{ $err ? 'border-red-500/60' : 'border-th-border' }} rounded-lg px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent" />
                        @endif

                        @if($err)
                        <p class="mt-1.5 text-[11px] text-red-400">{{ $err }}</p>
                        @endif
                    </div>
                    @endforeach

                    <button type="submit" class="inline-flex items-center justify-center gap-2 w-full bg-accent text-white px-6 py-3 rounded-xl text-[14px] font-semibold hover:bg-accent-h transition-colors mt-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689l9-3.939 9 3.939M4.5 9.75v9.75c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V9.75M9 22.5v-3.75A1.5 1.5 0 0110.5 17.25h3a1.5 1.5 0 011.5 1.5V22.5"/></svg>
                        {{ __('register.info_submit') }}
                    </button>
                </form>
            </div>
            @endif

            {{-- What happens next --}}
            <div class="bg-page border border-th-border rounded-xl p-5 sm:p-6 mb-7 sm:mb-8">
                <h3 class="text-[14px] sm:text-[15px] font-bold text-primary mb-5 flex items-center gap-2">
                    <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                    {{ __('success.next_title') }}
                </h3>

                <div class="space-y-5">
                    @php
                        $steps = [
                            ['1', __('success.next_step1_title'), __('success.next_step1_body'), 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z'],
                            ['2', __('success.next_step2_title'), __('success.next_step2_body'), 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                            ['3', __('success.next_step3_title'), __('success.next_step3_body'), 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ];
                    @endphp
                    @foreach($steps as $step)
                    <div class="flex items-start gap-4">
                        <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0 text-accent">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step[3] }}"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h4 class="text-[13.5px] sm:text-[14px] font-semibold text-primary mb-0.5">
                                <span class="text-faint font-mono me-1.5">{{ $step[0] }}.</span>{{ $step[1] }}
                            </h4>
                            <p class="text-[12.5px] sm:text-[13px] text-muted leading-relaxed">{{ $step[2] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Support contact --}}
            <div class="bg-accent/5 border border-accent/15 rounded-xl px-4 sm:px-5 py-4 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 mb-6">
                <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                <span class="text-[13px] text-muted">{{ __('success.questions') }}</span>
                <a href="mailto:support@trilink.ae" class="text-[13px] font-semibold text-accent hover:text-accent-h transition-colors">support@trilink.ae</a>
            </div>

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 w-full px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                        {{ __('success.sign_out') }}
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 w-full px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                    {{ __('success.go_to_login') }}
                </a>
            @endauth
        </div>

        {{-- Footer note --}}
        <p class="text-center text-[12.5px] sm:text-[13px] text-muted mt-6">{{ __('success.notify_footer') }}</p>
    </div>
</main>

<x-landing.footer />

@endsection
