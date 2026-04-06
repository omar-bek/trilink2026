@php $user = $user ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.first_name') }} *</label>
        <input type="text" name="first_name" value="{{ old('first_name', $user?->first_name) }}" required
               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.last_name') }}</label>
        <input type="text" name="last_name" value="{{ old('last_name', $user?->last_name) }}"
               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.email') }} *</label>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" required
               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}"
               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.role_col') }} *</label>
        <select name="role" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            @foreach(\App\Enums\UserRole::cases() as $r)
                <option value="{{ $r->value }}" @selected(old('role', $user?->role?->value) === $r->value)>{{ __('role.' . $r->value) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('common.status') }}</label>
        <select name="status" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            @foreach(\App\Enums\UserStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('status', $user?->status?->value ?? 'active') === $s->value)>{{ __('status.' . $s->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.users.company') }}</label>
        <select name="company_id" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            <option value="">— {{ __('admin.users.no_company') }} —</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(old('company_id', $user?->company_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-[12px] font-semibold text-body mb-1.5">
            {{ __('admin.users.password') }} {{ $user ? '(' . __('admin.users.leave_blank') . ')' : '*' }}
        </label>
        <input type="password" name="password" {{ $user ? '' : 'required' }} minlength="8"
               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
    </div>
</div>
