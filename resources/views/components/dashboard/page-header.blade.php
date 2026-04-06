@props([
    'title' => '',
    'subtitle' => '',
    'back' => null, // route name or url to go back
    'logo' => true, // show the brand logo next to the title
])

<div class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div class="min-w-0">
        @if($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary transition-colors mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back_to_dashboard') }}
        </a>
        @endif
        <div class="flex items-center gap-3">
            @if($logo)
            <img src="{{ asset('logo/logo.png') }}" alt="{{ config('app.name') }}"
                 class="h-9 w-auto flex-shrink-0 dark:brightness-100 brightness-0" />
            @endif
            <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight truncate">{{ $title }}</h1>
        </div>
        @if($subtitle)
        <p class="text-[14px] text-muted mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
    <div class="flex items-center gap-3">
        {{ $actions }}
    </div>
    @endif
</div>
