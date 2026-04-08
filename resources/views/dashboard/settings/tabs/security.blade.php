<h3 class="text-[18px] font-bold text-primary mb-6">{{ __('settings.security') }}</h3>

<form method="POST" action="{{ route('settings.security.update') }}" class="space-y-5 max-w-md">
    @csrf
    @method('PATCH')

    <h4 class="text-[14px] font-bold text-primary">{{ __('profile.change_password') }}</h4>

    <div>
        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('profile.current_password') }}</label>
        <input type="password" name="current_password" required
               class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
    </div>

    <div>
        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('auth.new_password') }}</label>
        <input type="password" name="password" required minlength="8"
               class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
    </div>

    <div>
        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('auth.confirm_password') }}</label>
        <input type="password" name="password_confirmation" required minlength="8"
               class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
    </div>

    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
        Update Password
    </button>
</form>

<div class="mt-10 pt-6 border-t border-th-border">
    <h4 class="text-[14px] font-bold text-primary mb-2">{{ __('two_factor.title') }}</h4>
    <p class="text-[12px] text-muted mb-4">
        @if(auth()->user()?->two_factor_confirmed_at)
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20 me-2">{{ __('two_factor.status_enabled') }}</span>
            {{ __('two_factor.enabled_subtitle') }}
        @else
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold text-muted bg-surface border border-th-border me-2">{{ __('two_factor.status_disabled') }}</span>
            {{ __('two_factor.setup_intro') }}
        @endif
    </p>
    <a href="{{ route('dashboard.two-factor.setup') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        {{ auth()->user()?->two_factor_confirmed_at ? __('two_factor.manage') : __('two_factor.enable_cta') }}
    </a>
</div>

<div class="mt-10 pt-6 border-t border-th-border">
    <h4 class="text-[14px] font-bold text-primary mb-2">Active Sessions</h4>
    <p class="text-[12px] text-muted">You are currently signed in on this device.</p>
</div>
