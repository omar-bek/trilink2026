@props(['branch' => null, 'categories' => collect(), 'candidates' => collect()])

<div class="bg-surface border border-th-border rounded-2xl p-6 space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.name') }}</label>
            <input type="text" name="name" required value="{{ old('name', $branch?->name) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.name_ar') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $branch?->name_ar) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.category') }}</label>
            <select name="category_id"
                    class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                <option value="">— {{ __('common.select') }} —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $branch?->category_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.manager') }}</label>
            <select name="branch_manager_id"
                    class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                <option value="">— {{ __('common.select') }} —</option>
                @foreach($candidates as $u)
                    <option value="{{ $u->id }}" @selected(old('branch_manager_id', $branch?->branch_manager_id) == $u->id)>
                        {{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }} ({{ $u->email }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.address') }}</label>
            <input type="text" name="address" value="{{ old('address', $branch?->address) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.city') }}</label>
                <input type="text" name="city" value="{{ old('city', $branch?->city) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('branches.country') }}</label>
                <input type="text" name="country" maxlength="2" value="{{ old('country', $branch?->country) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary uppercase focus:outline-none focus:border-accent" />
            </div>
        </div>
    </div>

    <label class="flex items-center gap-2 text-[13px] text-primary">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true))>
        {{ __('branches.active') }}
    </label>
</div>
