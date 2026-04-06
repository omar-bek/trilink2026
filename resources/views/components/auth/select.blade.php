@props([
    'label' => '',
    'name' => '',
    'required' => false,
    'placeholder' => 'Select...',
    'options' => [],
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
    <div class="relative">
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => 'w-full bg-page border rounded-lg px-4 py-3 text-[14px] text-primary appearance-none focus:outline-none focus:ring-2 transition-all cursor-pointer pr-10 ' . ($error ? 'border-red-500/60 focus:border-red-500 focus:ring-red-500/10' : 'border-th-border focus:border-accent/50 focus:ring-accent/10')]) }}
        >
            <option value="" class="text-faint">{{ $placeholder }}</option>
            @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $resolved === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
        <svg class="w-4 h-4 text-muted absolute end-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
    </div>
    @if($error)
    <p class="mt-1.5 text-[11px] text-red-400">{{ $error }}</p>
    @endif
</div>
