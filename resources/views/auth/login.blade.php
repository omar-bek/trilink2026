@extends('layouts.app')

@section('title', 'Login - TriLink Trading')

@section('content')

<x-landing.navbar />

<main class="pt-32 pb-20 px-6 lg:px-10 bg-page min-h-screen">
    <div class="max-w-[520px] mx-auto">
        <div class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10">
            <div class="mb-8">
                <h1 class="text-[32px] sm:text-[36px] font-bold text-primary mb-2">Welcome back</h1>
                <p class="text-[15px] text-muted">Sign in to access your TriLink dashboard.</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-[13px] text-[#FCA5A5]">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-[13px] font-semibold text-primary mb-2">Email Address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="w-full rounded-lg border border-th-border bg-page px-4 py-3 text-[14px] text-primary placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/30"
                        placeholder="you@company.com"
                    />
                </div>

                <div>
                    <label for="password" class="block text-[13px] font-semibold text-primary mb-2">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="w-full rounded-lg border border-th-border bg-page px-4 py-3 text-[14px] text-primary placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/30"
                        placeholder="Enter your password"
                    />
                </div>

                <label class="inline-flex items-center gap-2 text-[13px] text-muted">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="h-4 w-4 rounded border-th-border bg-page text-accent focus:ring-accent/40"
                    />
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full px-6 py-3 rounded-lg text-[14px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(37,99,235,0.25)]"
                >
                    Login
                </button>
            </form>

            <p class="mt-6 text-[13px] text-muted">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-accent hover:underline">Create one</a>
            </p>
        </div>
    </div>
</main>

<x-landing.footer />

@endsection
