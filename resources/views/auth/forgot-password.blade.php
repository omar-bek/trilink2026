@extends('layouts.app')
@section('title', __('auth.reset_password'))

@section('content')

<x-landing.navbar />

<main class="pt-32 pb-20 px-6 lg:px-10 bg-page min-h-screen">
    <div class="max-w-[480px] mx-auto">
        <div class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10">
            <div class="text-center mb-8">
                <h1 class="text-[28px] font-bold text-primary mb-2">{{ __('auth.forgot_password') }}</h1>
                <p class="text-[13px] text-muted">{{ __('auth.email_address') }}</p>
            </div>

            @if(session('status'))
            <div class="mb-5 bg-[#10B981]/5 border border-[#10B981]/30 rounded-xl p-4 text-[13px] text-[#10B981]">
                {{ session('status') }}
            </div>
            @endif

            @if($errors->any())
            <div class="mb-5 bg-[#EF4444]/5 border border-[#EF4444]/30 rounded-xl p-4 text-[13px] text-[#EF4444]">
                <ul class="list-disc ms-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('auth.email_address') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
                </div>
                <button type="submit" class="w-full px-5 py-3 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                    {{ __('auth.send_reset_link') }}
                </button>
            </form>

            <p class="text-center text-[12px] text-muted mt-6">
                <a href="{{ route('login') }}" class="text-accent hover:underline">{{ __('common.back') }}</a>
            </p>
        </div>
    </div>
</main>

<x-landing.footer />

@endsection
