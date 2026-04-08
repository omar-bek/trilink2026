@props([
    // The form action URL — every filter bar is a GET form submitting to
    // its own index page.
    'action' => '',
    // Current search string. Echoed into the input's `value=`.
    'search' => '',
    // Placeholder for the search input.
    'placeholder' => '',
    // Optional clear-filters URL. When provided AND any filter is active,
    // a "clear" button is rendered next to the submit button.
    'clearUrl' => null,
    // Whether any filter is currently applied — controls the clear button.
    'hasFilters' => false,
    // Optional result count to render at the end (e.g. ":count results").
    // When null, the count is omitted.
    'count' => null,
    // Translation key for the count line. Falls back to a generic "common.found".
    'countLabel' => 'common.found',
])

<form method="GET" action="{{ $action }}"
      class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3 items-stretch lg:items-center">
    {{-- Hidden inputs the parent page wants to preserve across submissions
         (typically the active status tab). --}}
    {{ $hidden ?? '' }}

    {{-- Search input. Always present — pass an empty placeholder to skip. --}}
    @if($placeholder !== null)
    <div class="flex-1 relative min-w-[220px]">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="{{ $search }}" placeholder="{{ $placeholder }}"
               class="w-full bg-page border border-th-border rounded-xl ps-11 pe-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40">
    </div>
    @endif

    {{-- Slot for any number of <select> filters specific to the page. --}}
    {{ $filters ?? '' }}

    <button type="submit"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.2)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/>
        </svg>
        {{ __('common.search') }}
    </button>

    @if($hasFilters && $clearUrl)
    <a href="{{ $clearUrl }}"
       class="inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-[12px] font-semibold text-muted bg-page border border-th-border hover:text-primary transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        {{ __('common.clear_filters') }}
    </a>
    @endif

    @if($count !== null)
    <span class="text-[12px] text-muted whitespace-nowrap">{{ __($countLabel, ['count' => $count]) }}</span>
    @endif
</form>
