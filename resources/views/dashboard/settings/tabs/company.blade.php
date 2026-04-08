<h3 class="text-[18px] font-bold text-primary mb-6">{{ __('settings.company_profile') }}</h3>

<form method="POST" action="{{ route('settings.company.update') }}" class="space-y-5">
    @csrf
    @method('PATCH')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">Company Name</label>
            <input type="text" name="name" value="{{ old('name', $company?->name) }}" required
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">Trade License Number</label>
            <input type="text" name="registration_number" value="{{ old('registration_number', $company?->registration_number) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[13px] font-semibold text-primary mb-2">Company Description</label>
            <textarea name="description" rows="4"
                      class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 resize-none">{{ old('description', $company?->description) }}</textarea>
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">Email Address</label>
            <input type="email" name="email" value="{{ old('email', $company?->email) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">Phone Number</label>
            <input type="tel" name="phone" value="{{ old('phone', $company?->phone) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[13px] font-semibold text-primary mb-2">Address</label>
            <input type="text" name="address" value="{{ old('address', $company?->address) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">City</label>
            <input type="text" name="city" value="{{ old('city', $company?->city) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-primary mb-2">Country</label>
            <input type="text" name="country" value="{{ old('country', $company?->country) }}"
                   class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
        </div>
    </div>

    <div class="pt-4 border-t border-th-border">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z"/></svg>
            Save Changes
        </button>
    </div>
</form>
