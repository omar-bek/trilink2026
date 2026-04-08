@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.success_title'))

@section('content')

<div class="min-h-[70vh] flex flex-col items-center justify-center px-4 py-10">

    @if(session('status'))
    <div class="w-full max-w-[460px] mb-6 rounded-xl border border-[#14B8A6]/30 bg-[#14B8A6]/10 text-[#14B8A6] px-4 py-3 text-[13px] text-center">
        {{ session('status') }}
    </div>
    @endif

    <div class="w-full max-w-[460px] bg-surface border border-th-border rounded-3xl px-8 sm:px-12 py-12 text-center shadow-[0_10px_40px_rgba(0,0,0,0.25)]">

        {{-- Success icon with confetti decorations --}}
        <div class="relative w-[180px] h-[180px] mx-auto mb-8">
            {{-- Confetti / decorative dots --}}
            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 180 180" fill="none" aria-hidden="true">
                {{-- Dots --}}
                <circle cx="30" cy="40" r="3.5" fill="#14B8A6"/>
                <circle cx="150" cy="35" r="2.5" fill="#14B8A6"/>
                <circle cx="160" cy="95" r="3" fill="#14B8A6"/>
                <circle cx="20" cy="110" r="2.5" fill="#14B8A6"/>
                <circle cx="55" cy="155" r="3" fill="#14B8A6"/>
                <circle cx="135" cy="150" r="2.5" fill="#14B8A6"/>
                {{-- Squiggles --}}
                <path d="M15 70 q5 -6 10 0 t10 0" stroke="#14B8A6" stroke-width="2" stroke-linecap="round" fill="none"/>
                <path d="M150 130 q5 -6 10 0 t10 0" stroke="#14B8A6" stroke-width="2" stroke-linecap="round" fill="none"/>
                <path d="M155 60 a10 10 0 0 1 8 12" stroke="#14B8A6" stroke-width="2" stroke-linecap="round" fill="none"/>
                <path d="M22 145 a10 10 0 0 1 8 -12" stroke="#14B8A6" stroke-width="2" stroke-linecap="round" fill="none"/>
            </svg>

            {{-- Center checkmark circle --}}
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-[96px] h-[96px] rounded-full bg-[#14B8A6] flex items-center justify-center shadow-[0_8px_24px_rgba(20,184,166,0.35)]">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Heading --}}
        <h1 class="text-[22px] sm:text-[24px] font-bold text-primary mb-2">
            {{ __('pr.success_title') }}
        </h1>
        <p class="text-[14px] text-muted mb-8">
            {{ __('pr.success_thanks') }}
        </p>

        {{-- Primary CTA --}}
        <a href="{{ route('dashboard') }}"
           class="block w-full text-center px-6 py-3.5 rounded-xl text-[14px] font-semibold text-white bg-[#14B8A6] hover:bg-[#0F9488] transition-colors shadow-[0_6px_18px_rgba(20,184,166,0.3)]">
            {{ __('pr.success_go_home') }}
        </a>

        {{-- Secondary action: view the just-created request --}}
        <a href="{{ route('dashboard.purchase-requests.show', ['id' => $pr['numeric_id']]) }}"
           class="mt-3 inline-block text-[13px] text-muted hover:text-primary transition-colors">
            {{ __('pr.success_view_request') }} · {{ $pr['id'] }}
        </a>
    </div>
</div>

@endsection
