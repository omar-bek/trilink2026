{{--
    Generic "explain why" modal — used by both the Decline and the
    Terminate flows. Both actions destroy a contract's lifecycle so we
    require a written reason of at least N characters before the form
    will submit. The reason gets appended to the contract description
    so the audit log preserves the full justification alongside the
    state change.

    Required props:
        $event_name   — the Alpine event the page dispatches to open
                        this modal (e.g. 'open-decline-modal')
        $title        — modal heading
        $subtitle     — short explanation under the heading
        $action_url   — the form POST URL
        $button_label — submit button text
        $button_class — Tailwind classes for the submit button
        $min_length   — server-side minimum (5 for decline, 10 for terminate)
--}}
@props([
    'event_name',
    'title',
    'subtitle',
    'action_url',
    'button_label',
    'button_class' => 'bg-[#ef4444] hover:bg-[#dc2626]',
    'min_length' => 10,
])

<div
    x-data="{ open: false, reason: '' }"
    x-on:{{ $event_name }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-transition
        class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="pointer-events-auto w-full max-w-lg bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-2xl shadow-2xl overflow-hidden"
            @click.stop
        >
            <div class="flex items-start justify-between gap-3 p-6 border-b border-th-border dark:border-[rgba(255,255,255,0.08)]">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-[#ef4444]/15 text-[#ef4444] flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-[16px] font-bold text-primary dark:text-white">{{ $title }}</h3>
                        <p class="text-[12px] text-muted dark:text-[#b4b6c0] mt-1 leading-relaxed">{{ $subtitle }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="w-8 h-8 rounded-lg text-muted hover:text-primary dark:text-[#b4b6c0] dark:hover:text-white flex items-center justify-center flex-shrink-0"
                    aria-label="{{ __('common.close') }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ $action_url }}" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-[12px] font-semibold text-primary dark:text-white mb-2">
                        {{ __('contracts.reason_label') }}
                    </label>
                    <textarea
                        name="reason"
                        x-model="reason"
                        rows="4"
                        minlength="{{ $min_length }}"
                        maxlength="1000"
                        required
                        placeholder="{{ __('contracts.reason_placeholder') }}"
                        class="w-full bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-xl px-3 py-2.5 text-[13px] text-primary dark:text-white placeholder:text-faint dark:placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-accent/50 resize-none"
                    ></textarea>
                    <p class="text-[11px] text-muted dark:text-[#b4b6c0] mt-1.5">
                        <span x-text="reason.length"></span> / 1000 — {{ __('contracts.reason_min_chars', ['min' => $min_length]) }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-muted dark:text-[#b4b6c0] hover:text-primary dark:hover:text-white"
                    >
                        {{ __('contracts.amendment_cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="reason.length < {{ $min_length }}"
                        :class="reason.length < {{ $min_length }} ? 'opacity-50 cursor-not-allowed' : ''"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-bold text-white {{ $button_class }}"
                    >
                        {{ $button_label }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
