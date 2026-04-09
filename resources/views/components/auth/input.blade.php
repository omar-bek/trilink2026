@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'value' => '',
    'dir' => 'ltr',
])

@php
    // Repopulate from `old()` so a server-side validation failure does not
    // wipe everything the user typed. Falls back to the explicit `value`
    // prop, then to empty string.
    $resolved = old($name, $value);
    $error = $errors->first($name);
    $errorId = $name . '-error';

    // Sprint C.14 — error baseline classes. Previously the red border
    // only kicked in on focus, so a fresh page load with errors looked
    // identical to a clean form. Now an erred field has a permanent
    // red border, a faint red wash AND an inline icon — visible at a
    // glance even for colorblind users (the icon carries the meaning
    // independently of the colour).
    $baseClasses = 'w-full bg-page border rounded-lg px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:ring-2 transition-all';
    $stateClasses = $error
        ? 'border-red-500 bg-red-500/[0.03] focus:border-red-500 focus:ring-red-500/15 pe-10'
        : 'border-th-border focus:border-accent/50 focus:ring-accent/10';
@endphp

<div>
    @if($label)
    <label for="{{ $name }}" class="block text-[13px] font-semibold text-primary mb-2">
        {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
    </label>
    @endif
    <div class="relative">
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            dir="{{ $dir }}"
            value="{{ $resolved }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($error)
                aria-invalid="true"
                aria-describedby="{{ $errorId }}"
            @endif
            {{ $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) }}
        />
        @if($error)
            {{-- Error icon: dual-encodes the error state so colorblind
                 users (who can't rely on the red border) still see a
                 universally-understood "problem" symbol. Positioned at
                 the trailing edge of the input via `end-3` so it
                 mirrors automatically in RTL. --}}
            <span class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-red-500" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
                </svg>
            </span>
        @endif
    </div>
    @if($error)
    <p id="{{ $errorId }}" class="mt-1.5 text-[12px] text-red-500 font-medium flex items-center gap-1.5" role="alert">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
        </svg>
        <span>{{ $error }}</span>
    </p>
    @endif
</div>
