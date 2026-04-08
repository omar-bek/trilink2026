<h3 class="text-[18px] font-bold text-primary mb-6">{{ __('settings.personal_info') }}</h3>

<form method="POST" action="{{ route('settings.personal.update') }}" class="space-y-5">
    @csrf
    @method('PATCH')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
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
    </div>

    <div class="pt-4 border-t border-th-border">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
            Save Changes
        </button>
    </div>
</form>
