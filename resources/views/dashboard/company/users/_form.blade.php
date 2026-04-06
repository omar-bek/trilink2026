@php
    $user = $user ?? null;
    $currentRole       = old('role', $user?->role?->value ?? ($assignableRoles[0]->value ?? 'buyer'));
    $currentExtras     = (array) old('additional_roles', $user?->additional_roles ?? []);
    $currentPermsList  = (array) old('permissions', $user?->permissions ?? []);
@endphp

{{-- Identity --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('company.users.identity') }}</h3>
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
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('company.users.position') }}</label>
            <input type="text" name="position_title" value="{{ old('position_title', $user?->position_title) }}"
                   placeholder="{{ __('company.users.position_placeholder') }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
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
            <label class="block text-[12px] font-semibold text-body mb-1.5">
                {{ __('admin.users.password') }} {{ $user ? '(' . __('admin.users.leave_blank') . ')' : '*' }}
            </label>
            <input type="password" name="password" {{ $user ? '' : 'required' }} minlength="8"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
    </div>
</div>

{{-- Roles --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[15px] font-bold text-primary mb-1">{{ __('company.users.roles') }}</h3>
    <p class="text-[12px] text-muted mb-4">{{ __('company.users.roles_help') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('company.users.primary_role') }} *</label>
            <select name="role" id="primary-role" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                @foreach($assignableRoles as $r)
                    <option value="{{ $r->value }}" @selected($currentRole === $r->value)>{{ __('role.' . $r->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('company.users.additional_roles') }}</label>
            <div class="grid grid-cols-2 gap-2 bg-surface-2 border border-th-border rounded-lg p-3 max-h-40 overflow-y-auto">
                @foreach($assignableRoles as $r)
                    <label class="flex items-center gap-2 text-[12px] text-body">
                        <input type="checkbox" name="additional_roles[]" value="{{ $r->value }}" @checked(in_array($r->value, $currentExtras, true)) />
                        {{ __('role.' . $r->value) }}
                    </label>
                @endforeach
            </div>
            <p class="text-[11px] text-faint mt-1.5">{{ __('company.users.additional_roles_help') }}</p>
        </div>
    </div>
</div>

{{-- Permissions --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('company.users.permissions') }}</h3>
            <p class="text-[12px] text-muted">{{ __('company.users.permissions_help') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="apply-defaults"
                    class="text-[12px] font-semibold text-accent bg-accent/10 border border-accent/20 rounded-lg px-3 py-1.5 hover:bg-accent/20">
                {{ __('company.users.apply_defaults') }}
            </button>
            <button type="button" id="select-all-perms"
                    class="text-[12px] font-semibold text-body bg-surface-2 border border-th-border rounded-lg px-3 py-1.5 hover:text-primary">
                {{ __('company.users.select_all') }}
            </button>
            <button type="button" id="clear-all-perms"
                    class="text-[12px] font-semibold text-body bg-surface-2 border border-th-border rounded-lg px-3 py-1.5 hover:text-primary">
                {{ __('company.users.clear_all') }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($permissionCatalog as $group => $keys)
        <div class="bg-surface-2 border border-th-border rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-[12px] font-bold uppercase tracking-wider text-faint">{{ __('perm.group.' . $group) }}</h4>
                <button type="button" data-group-toggle="{{ $group }}" class="text-[10px] text-accent hover:underline">{{ __('company.users.toggle_group') }}</button>
            </div>
            <div class="space-y-2" data-group="{{ $group }}">
                @foreach($keys as $key)
                <label class="flex items-start gap-2 text-[12px] text-body">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" class="perm-checkbox mt-0.5" @checked(in_array($key, $currentPermsList, true)) />
                    <span>{{ __('perm.' . $key) }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
(function() {
    const roleDefaults = @json($roleDefaults);
    const roleSelect   = document.getElementById('primary-role');
    const allBoxes     = () => document.querySelectorAll('.perm-checkbox');

    const setBoxes = (keys) => {
        const set = new Set(keys);
        allBoxes().forEach(b => { b.checked = set.has(b.value); });
    };

    document.getElementById('apply-defaults')?.addEventListener('click', () => {
        const defaults = roleDefaults[roleSelect.value] || [];
        setBoxes(defaults);
    });

    document.getElementById('select-all-perms')?.addEventListener('click', () => {
        allBoxes().forEach(b => b.checked = true);
    });

    document.getElementById('clear-all-perms')?.addEventListener('click', () => {
        allBoxes().forEach(b => b.checked = false);
    });

    document.querySelectorAll('[data-group-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.getAttribute('data-group-toggle');
            const boxes = document.querySelectorAll(`[data-group="${group}"] .perm-checkbox`);
            const allOn = Array.from(boxes).every(b => b.checked);
            boxes.forEach(b => b.checked = !allOn);
        });
    });
})();
</script>
@endpush
