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

    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
        Update Password
    </button>
</form>

<div class="mt-10 pt-6 border-t border-th-border">
    <h4 class="text-[14px] font-bold text-primary mb-2">Active Sessions</h4>
    <p class="text-[12px] text-muted">You are currently signed in on this device.</p>
</div>
