@props(['url'])

{{--
    Reusable "Export CSV" link button used by the index pages added in
    Phase 0 / task 0.9. Pass `:url` already including any active query
    string + an `export=csv` parameter so the download keeps the same
    scope as whatever the user is currently filtering by.
--}}
<a href="{{ $url }}"
   class="inline-flex items-center gap-2 px-4 h-12 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
    </svg>
    {{ __('common.export_csv') }}
</a>
