@php $user = $user ?? null; @endphp

@php
// Centralised Tailwind input class so every field on the page lines up
// pixel-for-pixel with the global design tokens. Don't inline custom
// padding here — it WILL drift the moment a teammate copy-pastes.
$inputCls = 'w-full bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors';
$labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-faint mb-2';
@endphp

<div class="space-y-8">
    {{-- Section: Identity ─────────────────────────────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.users.section.identity') }}</h4>
                <p class="text-[11px] text-muted">{{ __('admin.users.section.identity_help') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.users.first_name') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <input type="text" name="first_name" required value="{{ old('first_name', $user?->first_name) }}" class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.users.last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user?->last_name) }}" class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.users.email') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <input type="email" name="email" required value="{{ old('email', $user?->email) }}" class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.users.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}" placeholder="+971 50 123 4567" class="{{ $inputCls }}" />
            </div>
        </div>
    </div>

    <div class="border-t border-th-border"></div>

    {{-- Section: Role & assignment ─────────────────────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.users.section.access') }}</h4>
                <p class="text-[11px] text-muted">{{ __('admin.users.section.access_help') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.users.role_col') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <select name="role" required class="{{ $inputCls }}">
                    @foreach(\App\Enums\UserRole::cases() as $r)
                        <option value="{{ $r->value }}" @selected(old('role', $user?->role?->value) === $r->value)>{{ __('role.' . $r->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('common.status') }}</label>
                <select name="status" class="{{ $inputCls }}">
                    @foreach(\App\Enums\UserStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(old('status', $user?->status?->value ?? 'active') === $s->value)>{{ __('status.' . $s->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="{{ $labelCls }}">{{ __('admin.users.company') }}</label>
                <select name="company_id" class="{{ $inputCls }}">
                    <option value="">— {{ __('admin.users.no_company') }} —</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(old('company_id', $user?->company_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-[11px] text-muted">{{ __('admin.users.company_help') }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-th-border"></div>

    {{-- Section: Credentials ──────────────────────────────────────────── --}}
    <div>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </div>
            <div>
                <h4 class="text-[14px] font-bold text-primary leading-tight">{{ __('admin.users.section.credentials') }}</h4>
                <p class="text-[11px] text-muted">{{ $user ? __('admin.users.section.credentials_help_edit') : __('admin.users.section.credentials_help_create') }}</p>
            </div>
        </div>

        <div>
            <label class="{{ $labelCls }}">
                {{ __('admin.users.password') }}
                @if(!$user)<span class="text-[#ff4d7f] normal-case">*</span>@endif
            </label>
            <input type="password" name="password" {{ $user ? '' : 'required' }} minlength="8"
                   placeholder="{{ $user ? __('admin.users.leave_blank') : '••••••••' }}"
                   class="{{ $inputCls }}" />
            <p class="mt-2 text-[11px] text-muted">{{ __('admin.users.password_help') }}</p>
        </div>
    </div>
</div>
