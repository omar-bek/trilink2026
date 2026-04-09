@extends('layouts.dashboard', ['active' => 'profile'])
@section('title', __('profile.title'))

@section('content')

<x-dashboard.page-header :title="__('profile.title')" :subtitle="trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

@if($errors->any())
<div class="mb-6 bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-xl p-4 text-[13px] text-[#ff4d7f]">
    <ul class="list-disc ms-5 space-y-1">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@if($user->company)
{{-- Company Logo --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6 mb-6">
    <div class="flex items-start justify-between gap-4 sm:gap-6 flex-wrap">
        <div>
            <h3 class="text-[16px] font-bold text-primary mb-1">{{ __('profile.company_logo') }}</h3>
            <p class="text-[12px] text-muted">{{ __('profile.company_logo_hint') }}</p>
            <p class="text-[11px] text-faint mt-1">{{ $user->company->name }}</p>
        </div>

        <form method="POST" action="{{ route('profile.company-logo') }}" enctype="multipart/form-data" class="flex items-center gap-4">
            @csrf
            <div class="w-20 h-20 rounded-2xl bg-page border border-th-border flex items-center justify-center overflow-hidden flex-shrink-0">
                @if($user->company->logo)
                    <img src="{{ asset('storage/' . $user->company->logo) }}" alt="{{ $user->company->name }}" class="w-full h-full object-cover">
                @else
                    <span class="text-[20px] font-bold text-accent">{{ strtoupper(substr($user->company->name, 0, 2)) }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-2">
                <label class="cursor-pointer">
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" required class="hidden" onchange="this.form.submit()">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        {{ __('profile.upload_logo') }}
                    </span>
                </label>
                <p class="text-[10px] text-faint">{{ __('profile.logo_size_hint') }}</p>
            </div>
        </form>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Profile info --}}
    <div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('profile.update') }}</h3>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.email') }}</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.phone') }}</label>
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('profile.update') }}
            </button>
        </form>
    </div>

    {{-- Password change --}}
    <div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('profile.change_password') }}</h3>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.current_password') }}</label>
                <input type="password" name="current_password" required
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('auth.new_password') }}</label>
                <input type="password" name="password" required
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('auth.confirm_password') }}</label>
                <input type="password" name="password_confirmation" required
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-accent-success hover:bg-accent-success/90 transition-all shadow-[0_10px_30px_-10px_rgba(0,217,181,0.4)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                {{ __('profile.change_password') }}
            </button>
        </form>
    </div>

    {{-- Sprint D.17 — notification preferences. The form is small on
         purpose: per-event filtering is a power-user feature that
         lives in /settings, not the profile. Here we expose the
         three controls that 95% of users care about: toggle email
         vs in-app, and a "daily digest / off" master switch. --}}
    @php
        $prefs = $user->notification_preferences ?? \App\Models\User::defaultNotificationPreferences();
        $channelDb   = (bool) ($prefs['channels']['database'] ?? true);
        $channelMail = (bool) ($prefs['channels']['mail']     ?? true);
        $digestMode  = $prefs['digest']['mode'] ?? 'realtime';

        // Per-category toggles. Defaults from the helper so the form
        // matches what notifications respect at runtime.
        $catDefaults = \App\Support\NotificationPreferences::DEFAULTS;
        $catSaved    = $user->custom_permissions['notifications'] ?? [];
        $categories  = [];
        foreach ($catDefaults as $key => $default) {
            $categories[$key] = is_array($catSaved) && array_key_exists($key, $catSaved)
                ? (bool) $catSaved[$key]
                : (bool) $default;
        }
    @endphp
    <div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-6">
        <h3 class="text-[16px] font-bold text-primary mb-1">{{ __('profile.notifications_title') }}</h3>
        <p class="text-[12px] text-muted mb-5">{{ __('profile.notifications_subtitle') }}</p>
        <form method="POST" action="{{ route('profile.notifications') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="space-y-3">
                <p class="text-[12px] font-semibold text-primary uppercase tracking-wider">{{ __('profile.notifications_channels') }}</p>

                <label class="flex items-start gap-3 p-3 rounded-xl bg-page border border-th-border cursor-pointer hover:border-accent/40">
                    <input type="checkbox" name="channel_database" value="1" @checked($channelDb)
                           class="mt-0.5 w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40">
                    <span class="text-[13px] text-body">
                        <span class="font-semibold text-primary block">{{ __('profile.notifications_in_app') }}</span>
                        <span class="text-[12px] text-muted">{{ __('profile.notifications_in_app_hint') }}</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-xl bg-page border border-th-border cursor-pointer hover:border-accent/40">
                    <input type="checkbox" name="channel_mail" value="1" @checked($channelMail)
                           class="mt-0.5 w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40">
                    <span class="text-[13px] text-body">
                        <span class="font-semibold text-primary block">{{ __('profile.notifications_email') }}</span>
                        <span class="text-[12px] text-muted">{{ __('profile.notifications_email_hint') }}</span>
                    </span>
                </label>
            </div>

            <div class="space-y-3">
                <p class="text-[12px] font-semibold text-primary uppercase tracking-wider">{{ __('profile.notifications_digest') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach (['realtime' => 'profile.notifications_digest_realtime', 'daily' => 'profile.notifications_digest_daily', 'off' => 'profile.notifications_digest_off'] as $value => $labelKey)
                        <label class="flex items-center gap-2 p-3 rounded-xl bg-page border cursor-pointer transition-colors {{ $digestMode === $value ? 'border-accent text-primary' : 'border-th-border text-body hover:border-accent/40' }}">
                            <input type="radio" name="digest_mode" value="{{ $value }}" @checked($digestMode === $value)
                                   class="w-4 h-4 text-accent focus:ring-accent/40">
                            <span class="text-[13px] font-semibold">{{ __($labelKey) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Per-category opt-in matrix. The Profile form previously
                 only exposed channel-level toggles, which meant a user
                 couldn't say "I want bid updates by email but compliance
                 alerts only in-app". This grid maps each category to a
                 single checkbox; the legacy NotificationPreferences
                 helper reads the same JSON shape so the toggles take
                 effect immediately for every notification. --}}
            <div class="space-y-3">
                <p class="text-[12px] font-semibold text-primary uppercase tracking-wider">{{ __('profile.notifications_categories') }}</p>
                <p class="text-[12px] text-muted">{{ __('profile.notifications_categories_hint') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($categories as $key => $enabled)
                        <label class="flex items-center gap-3 p-3 rounded-xl bg-page border border-th-border cursor-pointer hover:border-accent/40">
                            <input type="checkbox" name="categories[{{ $key }}]" value="1" @checked($enabled)
                                   class="w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40">
                            <span class="text-[13px] font-semibold text-primary">{{ __('profile.notification_category_' . $key) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-all">
                {{ __('profile.notifications_save') }}
            </button>
        </form>
    </div>
</div>

@endsection
