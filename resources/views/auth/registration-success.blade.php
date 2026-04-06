@extends('layouts.app')

@section('title', 'Registration Submitted - TriLink Trading')

@section('content')

<x-landing.navbar />

<main class="pt-32 pb-20 px-6 lg:px-10 bg-page min-h-screen">
    <div class="max-w-[680px] mx-auto">

        {{-- Success icon --}}
        <div class="flex justify-center mb-8">
            <div class="relative">
                <div class="absolute inset-0 blur-[40px] bg-[#22C55E]/20 rounded-full"></div>
                <div class="relative w-[88px] h-[88px] rounded-full bg-[#22C55E]/10 border border-[#22C55E]/30 flex items-center justify-center">
                    <svg class="w-12 h-12 text-[#22C55E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        {{-- Main card --}}
        <div class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10">
            {{-- Heading --}}
            <div class="text-center mb-8">
                <h1 class="text-[26px] sm:text-[30px] font-bold text-primary mb-3">Registration Submitted Successfully!</h1>
                <p class="text-[14px] text-muted leading-[1.7] max-w-[480px] mx-auto">Your company registration has been received and is currently under review by our admin team.</p>
                @auth
                <p class="mt-3 text-[12px] text-muted">
                    Signed in as <span class="text-primary font-semibold">{{ auth()->user()->email }}</span>
                </p>
                @endauth
            </div>

            {{-- Pending approval banner --}}
            <div class="bg-[#F59E0B]/5 border border-[#F59E0B]/20 rounded-xl px-6 py-5 text-center mb-8">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <svg class="w-5 h-5 text-[#F59E0B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <h3 class="text-[16px] font-bold text-primary">Pending Approval</h3>
                </div>
                <p class="text-[13px] text-muted">Expected review time: 24-48 hours</p>
            </div>

            {{-- What happens next --}}
            <div class="bg-page border border-th-border rounded-xl p-6 mb-8">
                <h3 class="text-[15px] font-bold text-primary mb-5">What happens next?</h3>

                <div class="space-y-5">
                    @foreach([
                        ['1', 'Document Review', 'Our team will verify your company documents and information'],
                        ['2', 'Email Notification', "You'll receive an email about your approval status"],
                        ['3', 'Account Activation', 'Once approved, you can start using the platform immediately'],
                    ] as $item)
                    <div class="flex items-start gap-4">
                        <div class="w-7 h-7 rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                            <span class="text-[12px] font-bold text-accent">{{ $item[0] }}</span>
                        </div>
                        <div class="flex-1 pt-0.5">
                            <h4 class="text-[14px] font-semibold text-primary mb-0.5">{{ $item[1] }}</h4>
                            <p class="text-[13px] text-muted leading-[1.6]">{{ $item[2] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Support contact --}}
            <div class="bg-accent/5 border border-accent/15 rounded-xl px-5 py-4 flex items-center justify-center gap-2 mb-6">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                <span class="text-[13px] text-muted">Questions?</span>
                <a href="mailto:support@trilink.ae" class="text-[13px] font-semibold text-accent hover:underline">support@trilink.ae</a>
            </div>

            @auth
            {{-- The user is auto-logged-in but cannot reach the dashboard
                 until an admin approves the company. Sign out is the only
                 meaningful action available here. --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full text-center px-6 py-3.5 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                    Sign Out
                </button>
            </form>
            @else
            <a href="{{ route('login') }}" class="block w-full text-center px-6 py-3.5 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                Go to Login
            </a>
            @endauth
        </div>

        {{-- Footer note --}}
        <p class="text-center text-[13px] text-muted mt-6">We'll notify you at the email address provided during registration</p>
    </div>
</main>

<x-landing.footer />

@endsection
