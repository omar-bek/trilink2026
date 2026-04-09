{{--
    Internal team notes panel — drops into the sidebar of both buyer
    and supplier contract show pages. Notes are visible ONLY to other
    users of the SAME company as the author; the counter-party never
    sees them. Used for procurement strategy ("price 15% above market,
    push back next round"), escalation triggers, and any commentary
    that should not leak to the other side of the contract.

    Required props:
        $contract_id
        $internal_notes — array shape from ContractController::show()
                          with id, body, author, when, is_mine
--}}
@props(['contract_id', 'internal_notes'])

<div
    x-data="{
        open: false,
        body: '',
        get charsLeft() { return 2000 - this.body.length; },
    }"
    class="bg-surface border border-th-border rounded-2xl p-6"
>
    <div class="flex items-start justify-between gap-3 mb-4">
        <div class="flex items-start gap-2">
            <div class="w-8 h-8 rounded-lg bg-accent-violet/15 text-accent-violet flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('contracts.internal_notes_title') }}</h3>
                <p class="text-[10px] text-muted mt-0.5">{{ __('contracts.internal_notes_subtitle') }}</p>
            </div>
        </div>
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open.toString()"
            class="text-[11px] font-semibold text-accent hover:text-accent-h"
        >
            <span x-show="!open">+ {{ __('contracts.internal_notes_add') }}</span>
            <span x-show="open" x-cloak>{{ __('common.close') }}</span>
        </button>
    </div>

    {{-- Add note form --}}
    <form
        x-show="open"
        x-cloak
        x-transition
        method="POST"
        action="{{ route('dashboard.contracts.internal-notes.store', ['id' => $contract_id]) }}"
        class="mb-4 p-3 bg-page border border-th-border rounded-xl"
    >
        @csrf
        <textarea
            name="body"
            x-model="body"
            rows="3"
            maxlength="2000"
            required
            placeholder="{{ __('contracts.internal_notes_placeholder') }}"
            class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none"
        ></textarea>
        <div class="flex items-center justify-between mt-2">
            <span class="text-[10px] text-muted" x-text="charsLeft + ' / 2000'"></span>
            <button
                type="submit"
                :disabled="body.length === 0"
                :class="body.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-accent-violet hover:bg-accent-violet/90"
            >
                {{ __('contracts.internal_notes_save') }}
            </button>
        </div>
    </form>

    {{-- Notes list --}}
    @if(empty($internal_notes))
        <p class="text-[11px] text-muted italic text-center py-4">
            {{ __('contracts.internal_notes_empty') }}
        </p>
    @else
        <div class="space-y-3">
            @foreach($internal_notes as $note)
                <div class="bg-page border border-th-border rounded-xl p-3">
                    <p class="text-[12px] text-primary leading-[18px] whitespace-pre-wrap">{{ $note['body'] }}</p>
                    <div class="flex items-center justify-between mt-2">
                        <p class="text-[10px] text-muted">{{ $note['author'] }} · {{ $note['when'] }}</p>
                        @if($note['is_mine'])
                        <form method="POST" action="{{ route('dashboard.contracts.internal-notes.destroy', ['id' => $contract_id, 'noteId' => $note['id']]) }}"
                              onsubmit="return confirm('{{ __('contracts.internal_notes_delete_confirm') }}');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[10px] font-semibold text-muted hover:text-accent-danger">
                                {{ __('common.delete') }}
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
