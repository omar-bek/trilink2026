@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', '403 — ' . __('errors.forbidden'))

@section('content')

<div class="min-h-[60vh] flex items-center justify-center">
    <div class="max-w-md text-center">
        <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-[#EF4444]/10 border border-[#EF4444]/20 flex items-center justify-center">
            <svg class="w-10 h-10 text-[#EF4444]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>
        <p class="text-[12px] font-mono text-muted mb-2">403</p>
        <h1 class="text-[28px] font-bold text-primary mb-3">{{ __('errors.forbidden_title') }}</h1>
        <p class="text-[14px] text-muted leading-relaxed mb-6">
            {{ $message ?: __('errors.forbidden_default') }}
        </p>
        <div class="flex items-center justify-center gap-3">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 12L11.204 3.045a1.125 1.125 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                {{ __('common.go_home') }}
            </a>
            <a href="javascript:history.back()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                {{ __('common.go_back') }}
            </a>
        </div>
    </div>
</div>

@endsection
