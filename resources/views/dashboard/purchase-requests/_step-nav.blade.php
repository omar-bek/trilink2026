{{-- Reusable step navigation footer for the PR wizard.
     $step  = current step number (1-4)
     $total = total steps (4) --}}
<div class="mt-8 pt-5 border-t border-th-border flex items-center justify-between gap-3 flex-wrap">
    @if($step > 1)
    <button type="button" onclick="prevStep({{ $step - 1 }})" class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('common.previous') }}
    </button>
    @else
    <div></div>
    @endif

    <div class="flex items-center gap-3">
        <button type="button" onclick="saveDraft()" class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            {{ __('common.save_draft') }}
        </button>
        @if($step < $total)
        <button type="button" onclick="nextStep({{ $step + 1 }})" class="inline-flex items-center gap-2 h-11 px-5 rounded-[12px] text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_10px_30px_-12px_rgba(79,124,255,0.55)] transition-all">
            {{ __('common.next') }}
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
        </button>
        @endif
    </div>
</div>
