<h3 class="text-[18px] font-bold text-primary mb-1">{{ __('settings.security_policy_title') }}</h3>
<p class="text-[13px] text-muted mb-6">{{ __('settings.security_policy_subtitle') }}</p>

@unless($canManageSecurity)
<div class="mb-6 bg-[#ffc24d]/5 border border-[#ffc24d]/30 rounded-xl p-4 text-[13px] text-[#ffc24d]">
    {{ __('settings.manager_only_notice') }}
</div>
@endunless

<form method="POST" action="{{ route('settings.security-policy.update') }}" class="space-y-6" @unless($canManageSecurity) onsubmit="return false" @endunless>
    @csrf
    @method('PATCH')
    <fieldset @unless($canManageSecurity) disabled class="opacity-60 pointer-events-none" @endunless class="space-y-6">

    {{-- 2FA enforcement --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.two_factor_enforcement') }}</h4>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="enforce_two_factor" value="1"
                   @checked(old('enforce_two_factor', $securityPolicy->enforce_two_factor))
                   class="mt-1 w-4 h-4 rounded border-th-border text-accent">
            <div class="flex-1">
                <span class="text-[13px] font-semibold text-primary">{{ __('settings.enforce_two_factor') }}</span>
                <p class="text-[12px] text-muted mt-1">{{ __('settings.enforce_two_factor_hint') }}</p>
            </div>
        </label>
        <div class="mt-3 ms-7">
            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.two_factor_grace_days') }}</label>
            <input type="number" min="0" max="30" name="two_factor_grace_days"
                   value="{{ old('two_factor_grace_days', $securityPolicy->two_factor_grace_days) }}"
                   class="w-40 bg-page border border-th-border rounded-xl px-4 py-2 text-[13px] text-primary">
        </div>
    </div>

    {{-- Password policy --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.password_policy') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.password_min_length') }}</label>
                <input type="number" min="8" max="64" name="password_min_length"
                       value="{{ old('password_min_length', $securityPolicy->password_min_length) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.password_rotation_days') }}</label>
                <input type="number" min="30" max="365" name="password_rotation_days"
                       value="{{ old('password_rotation_days', $securityPolicy->password_rotation_days) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary"
                       placeholder="{{ __('settings.never_expires') }}">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.password_history_count') }}</label>
                <input type="number" min="0" max="24" name="password_history_count"
                       value="{{ old('password_history_count', $securityPolicy->password_history_count) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
        </div>
        <div class="mt-3 space-y-2">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="password_require_mixed_case" value="1"
                       @checked(old('password_require_mixed_case', $securityPolicy->password_require_mixed_case))
                       class="w-4 h-4 rounded border-th-border text-accent">
                <span class="text-[13px] text-primary">{{ __('settings.require_mixed_case') }}</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="password_require_number" value="1"
                       @checked(old('password_require_number', $securityPolicy->password_require_number))
                       class="w-4 h-4 rounded border-th-border text-accent">
                <span class="text-[13px] text-primary">{{ __('settings.require_number') }}</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="password_require_symbol" value="1"
                       @checked(old('password_require_symbol', $securityPolicy->password_require_symbol))
                       class="w-4 h-4 rounded border-th-border text-accent">
                <span class="text-[13px] text-primary">{{ __('settings.require_symbol') }}</span>
            </label>
        </div>
    </div>

    {{-- Session policy --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.session_policy') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.session_idle_timeout') }}</label>
                <input type="number" min="5" max="1440" name="session_idle_timeout_minutes"
                       value="{{ old('session_idle_timeout_minutes', $securityPolicy->session_idle_timeout_minutes) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.session_absolute_max') }}</label>
                <input type="number" min="1" max="720" name="session_absolute_max_hours"
                       value="{{ old('session_absolute_max_hours', $securityPolicy->session_absolute_max_hours) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
        </div>
    </div>

    {{-- Login throttling --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.login_throttling') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.max_login_attempts') }}</label>
                <input type="number" min="3" max="20" name="max_login_attempts"
                       value="{{ old('max_login_attempts', $securityPolicy->max_login_attempts) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.lockout_minutes') }}</label>
                <input type="number" min="1" max="1440" name="lockout_minutes"
                       value="{{ old('lockout_minutes', $securityPolicy->lockout_minutes) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
        </div>
    </div>

    {{-- IP allowlist --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.ip_allowlist') }}</h4>
        <label class="flex items-start gap-3 cursor-pointer mb-3">
            <input type="checkbox" name="ip_allowlist_enabled" value="1"
                   @checked(old('ip_allowlist_enabled', $securityPolicy->ip_allowlist_enabled))
                   class="mt-1 w-4 h-4 rounded border-th-border text-accent">
            <div class="flex-1">
                <span class="text-[13px] font-semibold text-primary">{{ __('settings.enable_ip_allowlist') }}</span>
                <p class="text-[12px] text-muted mt-1">{{ __('settings.ip_allowlist_hint') }}</p>
            </div>
        </label>
        <textarea name="ip_allowlist" rows="4"
                  class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary font-mono"
                  placeholder="10.0.0.0/8&#10;192.168.1.0/24">{{ old('ip_allowlist', collect($securityPolicy->ip_allowlist ?? [])->implode("\n")) }}</textarea>
    </div>

    {{-- Email domain restriction --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.allowed_email_domains') }}</h4>
        <textarea name="allowed_email_domains" rows="3"
                  class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary font-mono"
                  placeholder="acme.com&#10;acme-uae.com">{{ old('allowed_email_domains', collect($securityPolicy->allowed_email_domains ?? [])->implode("\n")) }}</textarea>
        <p class="text-[12px] text-muted mt-2">{{ __('settings.allowed_email_domains_hint') }}</p>
    </div>

    {{-- Audit retention --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.audit_retention') }}</h4>
        <input type="number" min="30" max="3650" name="audit_retention_days"
               value="{{ old('audit_retention_days', $securityPolicy->audit_retention_days) }}"
               class="w-64 bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary"
               placeholder="{{ __('settings.keep_forever') }}">
    </div>

    <div class="pt-4 border-t border-th-border">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h disabled:opacity-50" @unless($canManageSecurity) disabled @endunless>
            {{ __('settings.save') }}
        </button>
    </div>
    </fieldset>
</form>
