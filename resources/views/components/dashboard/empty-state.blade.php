@props([
    'title'    => null,
    'message'  => null,
    'cta'      => null,
    'ctaUrl'   => null,
    'icon'     => null,
])

{{--
    Reusable "you have no X yet" empty state. Used by every index page that
    can show zero rows (Phase 0 / task 0.10). Same shape across the dashboard
    so the user learns one mental model: icon → title → one-line hint → CTA.
--}}
<div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-14 text-center">
    <div class="mx-auto w-16 h-16 rounded-full bg-page border border-th-border flex items-center justify-center mb-4">
        <svg class="w-7 h-7 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            @if($icon)
                {!! $icon !!}
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            @endif
        </svg>
    </div>

    @if($title)
        <h3 class="text-[16px] font-bold text-primary mb-1">{{ $title }}</h3>
    @endif

    @if($message)
        <p class="text-[13px] text-muted max-w-md mx-auto mb-5">{{ $message }}</p>
    @endif

    @if($cta && $ctaUrl)
        <a href="{{ $ctaUrl }}"
           class="inline-flex items-center gap-2 px-5 h-11 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ $cta }}
        </a>
    @endif

    {{-- Optional inner slot for callers that want custom content under the message --}}
    {{ $slot }}
</div>
