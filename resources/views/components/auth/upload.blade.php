@props([
    'label' => '',
    'description' => '',
    'name' => '',
    'required' => false,
    'accept' => '.pdf,.jpg,.jpeg,.png',
])

@php
    // The HTML `required` attribute is intentionally NOT applied here:
    // browsers do not preserve file inputs across server validation
    // failures, so any other field's error would re-empty this input and
    // the user would be unable to advance past this step. The `required`
    // prop only controls the visual asterisk and the server-side rule.
    $error = $errors->first($name);
@endphp

<div class="bg-page border {{ $error ? 'border-red-500/40' : 'border-th-border' }} rounded-xl p-6">
    <h4 class="text-[15px] font-bold text-primary mb-1">
        {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
    </h4>
    @if($description)
    <p class="text-[13px] text-muted mb-4">{{ $description }}</p>
    @endif

    <label for="{{ $name }}" class="block cursor-pointer">
        <div class="border-2 border-dashed {{ $error ? 'border-red-500/50' : 'border-th-border hover:border-accent/40' }} rounded-xl py-10 px-6 text-center transition-colors group">
            <svg class="w-8 h-8 text-muted group-hover:text-accent mx-auto mb-3 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            <p class="text-[14px] text-primary font-medium" data-upload-label="{{ $name }}">Click to upload or drag and drop</p>
        </div>
        <input type="file" id="{{ $name }}" name="{{ $name }}" class="hidden" accept="{{ $accept }}"
               onchange="document.querySelector('[data-upload-label=\'{{ $name }}\']').textContent = this.files[0]?.name || 'Click to upload or drag and drop'" />
    </label>

    <p class="text-[12px] text-faint mt-3">Supported formats: PDF, JPG, PNG (Max 5MB)</p>
    @if($error)
    <p class="mt-2 text-[11px] text-red-400">{{ $error }}</p>
    @endif
</div>
