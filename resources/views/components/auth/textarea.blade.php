@props([
    'label' => '',
    'name' => '',
    'placeholder' => '',
    'required' => false,
    'rows' => 3,
    'value' => '',
])

@php
    $resolved = old($name, $value);
    $error = $errors->first($name);
@endphp

<div>
    @if($label)
    <label for="{{ $name }}" class="block text-[13px] font-semibold text-primary mb-2">
        {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
    </label>
    @endif
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'w-full bg-page border rounded-lg px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:ring-2 transition-all resize-none ' . ($error ? 'border-red-500/60 focus:border-red-500 focus:ring-red-500/10' : 'border-th-border focus:border-accent/50 focus:ring-accent/10')]) }}
    >{{ $resolved }}</textarea>
    @if($error)
    <p class="mt-1.5 text-[11px] text-red-400">{{ $error }}</p>
    @endif
</div>
