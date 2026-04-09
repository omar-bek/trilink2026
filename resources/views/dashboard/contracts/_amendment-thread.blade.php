{{--
    Per-amendment discussion thread + send-message form. Drops into
    both the buyer (light theme) and supplier (dark theme) contract
    show pages — the colour tokens fall back to the dashboard CSS
    variables so each surface picks up its own palette automatically.

    Required props:
        $contract_id — numeric contract id (used by the action URL)
        $amendment   — array shape produced by ContractController::show()
                       with keys: id, messages[], message_count, can_message,
                       is_pending, proposed_by_me

    Behavior upgrades over the v1 stub:
      • Enter sends, Shift+Enter inserts a newline (chat-style).
      • The thread polls the JSON endpoint every 10 seconds while open
        so the other party's replies appear without a page refresh.
      • The proposer of a still-pending amendment gets a Cancel button
        that withdraws the proposal cleanly.
--}}
@props(['contract_id', 'amendment'])

{{-- Register the Alpine component once per page. The
     `__amendmentThreadInit` flag prevents re-registration when the
     partial is included multiple times (one per amendment row). --}}
@once
<script>
    document.addEventListener('alpine:init', () => {
        if (window.__amendmentThreadInit) return;
        window.__amendmentThreadInit = true;
        Alpine.data('amendmentThread', (config) => ({
            openThread: false,
            polling: false,
            messages: [],
            lastFetchedAt: null,
            pollHandle: null,
            hasMoreEarlier: false,
            loadingEarlier: false,
            init() {
                this.messages = config.initialMessages || [];
                this.hasMoreEarlier = !!config.hasMoreEarlier;
                this.lastFetchedAt = new Date().toISOString();
                // Pause polling whenever the tab is hidden (background
                // tab, locked screen, switched apps) to avoid burning
                // mobile battery and bandwidth on a thread the user
                // isn't even looking at. Resume on visibilitychange.
                this._visibilityHandler = () => {
                    if (!this.openThread) return;
                    if (document.hidden) {
                        this.stopPolling();
                    } else {
                        this.startPolling();
                        // Catch up on anything we missed while hidden.
                        this.fetchNew();
                    }
                };
                document.addEventListener('visibilitychange', this._visibilityHandler);
            },
            destroy() {
                this.stopPolling();
                if (this._visibilityHandler) {
                    document.removeEventListener('visibilitychange', this._visibilityHandler);
                }
            },
            toggle() {
                this.openThread = !this.openThread;
                if (this.openThread) {
                    this.startPolling();
                    this.$nextTick(() => {
                        if (this.$refs.bubbles) {
                            this.$refs.bubbles.scrollTop = this.$refs.bubbles.scrollHeight;
                        }
                    });
                } else {
                    this.stopPolling();
                }
            },
            startPolling() {
                // Don't poll when the tab is hidden — wait until the
                // user actually returns to the page.
                if (document.hidden) return;
                this.polling = true;
                if (this.pollHandle) clearInterval(this.pollHandle);
                this.pollHandle = setInterval(() => this.fetchNew(), 10000);
            },
            stopPolling() {
                this.polling = false;
                if (this.pollHandle) clearInterval(this.pollHandle);
                this.pollHandle = null;
            },
            async fetchNew() {
                try {
                    const url = config.pollUrl + '?since=' + encodeURIComponent(this.lastFetchedAt || '');
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const fresh = (data.messages || []).filter(m => !this.messages.find(existing => existing.id === m.id));
                    if (fresh.length > 0) {
                        this.messages = this.messages.concat(fresh);
                        this.$nextTick(() => {
                            if (this.$refs.bubbles) {
                                this.$refs.bubbles.scrollTop = this.$refs.bubbles.scrollHeight;
                            }
                        });
                    }
                    this.lastFetchedAt = data.now || new Date().toISOString();
                } catch (e) {
                    /* Network blip — try again on the next interval. */
                }
            },
            // Load older messages on demand. Capture the current
            // scroll position so the new messages are PREPENDED
            // without jumping the user away from where they were
            // reading. Stops calling itself when a fetch returns
            // fewer than the page size (the user has scrolled to
            // the very first message of the thread).
            async loadEarlier() {
                if (this.loadingEarlier || !this.hasMoreEarlier) return;
                this.loadingEarlier = true;
                try {
                    const oldestId = this.messages.length > 0 ? this.messages[0].id : null;
                    if (!oldestId) { this.loadingEarlier = false; return; }
                    const url = config.pollUrl + '?before=' + encodeURIComponent(oldestId);
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) { this.loadingEarlier = false; return; }
                    const data = await res.json();
                    const earlier = (data.messages || []).filter(m => !this.messages.find(existing => existing.id === m.id));
                    if (earlier.length === 0) {
                        this.hasMoreEarlier = false;
                    } else {
                        // Preserve scroll position: snapshot the
                        // scrollHeight before the prepend, and
                        // restore the relative offset after the
                        // DOM has updated.
                        const bubbles = this.$refs.bubbles;
                        const prevHeight = bubbles ? bubbles.scrollHeight : 0;
                        this.messages = earlier.concat(this.messages);
                        if (earlier.length < 20) {
                            this.hasMoreEarlier = false;
                        }
                        this.$nextTick(() => {
                            if (bubbles) {
                                bubbles.scrollTop = bubbles.scrollHeight - prevHeight;
                            }
                        });
                    }
                } catch (e) {
                    /* Ignore — the button stays available for retry. */
                } finally {
                    this.loadingEarlier = false;
                }
            },
        }));
    });
</script>
@endonce

<div
    x-data="amendmentThread({
        amendmentId: {{ $amendment['id'] }},
        pollUrl: '{{ route('dashboard.contracts.amendments.messages.poll', ['id' => $contract_id, 'amendmentId' => $amendment['id']]) }}',
        initialMessages: @js($amendment['messages']),
        hasMoreEarlier: @js((bool) ($amendment['has_more_messages'] ?? false)),
    })"
    class="mt-3"
    id="amendment-{{ $amendment['id'] }}"
>
    <div class="flex items-center gap-2 flex-wrap">
        <button
            type="button"
            @click="toggle()"
            class="inline-flex items-center gap-2 text-[12px] font-semibold text-accent hover:text-accent-h"
            :aria-expanded="openThread.toString()"
            aria-controls="amendment-{{ $amendment['id'] }}-panel"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
            </svg>
            <span x-show="!openThread">{{ __('contracts.negotiation_open') }}</span>
            <span x-show="openThread" x-cloak>{{ __('contracts.negotiation_close') }}</span>
            <span class="text-[10px] font-bold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">
                <span x-text="messages.length"></span> {{ __('contracts.message_singular') }}
            </span>
        </button>

        @if(!empty($amendment['is_pending']) && !empty($amendment['proposed_by_me']))
        <form method="POST" action="{{ route('dashboard.contracts.amendments.cancel', ['id' => $contract_id, 'amendmentId' => $amendment['id']]) }}"
              onsubmit="return confirm('{{ __('contracts.amendment_confirm_cancel') }}');" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#ef4444] hover:underline">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                {{ __('contracts.amendment_withdraw') }}
            </button>
        </form>
        @endif
    </div>

    <div
        id="amendment-{{ $amendment['id'] }}-panel"
        x-show="openThread"
        x-cloak
        x-transition
        class="mt-3 p-4 bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.08)] rounded-xl"
    >
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            <div class="flex-1">
                <p class="text-[12px] font-bold text-primary dark:text-white">{{ __('contracts.negotiation_title') }}</p>
                <p class="text-[10px] text-muted dark:text-[#b4b6c0]">{{ __('contracts.negotiation_subtitle') }}</p>
            </div>
            <span x-show="polling" x-cloak class="inline-flex items-center gap-1 text-[10px] text-muted dark:text-[#b4b6c0]">
                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                {{ __('contracts.live') }}
            </span>
        </div>

        {{-- Message bubbles — populated from Alpine state so polling
             can append new ones without re-rendering server-side. --}}
        <div x-ref="bubbles" class="space-y-3 mb-4 max-h-[320px] overflow-y-auto pe-1">
            {{-- Load earlier — visible only when the seed list
                 was capped at 20 and the server has more older
                 messages waiting. --}}
            <template x-if="hasMoreEarlier">
                <div class="text-center pb-2">
                    <button type="button"
                            @click="loadEarlier()"
                            :disabled="loadingEarlier"
                            :class="loadingEarlier ? 'opacity-50 cursor-not-allowed' : ''"
                            class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-accent hover:text-accent-h">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                        <span x-show="!loadingEarlier">{{ __('contracts.negotiation_load_earlier') }}</span>
                        <span x-show="loadingEarlier" x-cloak>{{ __('common.loading') }}…</span>
                    </button>
                </div>
            </template>

            <template x-for="message in messages" :key="message.id">
                <div :class="message.is_mine ? 'flex items-start justify-end gap-2' : 'flex items-start gap-2'">
                    <template x-if="!message.is_mine">
                        <div class="w-7 h-7 rounded-full bg-[#10B981]/15 text-[#10B981] flex items-center justify-center flex-shrink-0 text-[10px] font-bold uppercase" aria-hidden="true" x-text="(message.author || '?').slice(0,1)"></div>
                    </template>
                    <div class="max-w-[80%]">
                        <div :class="message.is_mine
                            ? 'bg-accent text-white rounded-2xl rounded-tr-sm px-3.5 py-2.5'
                            : 'bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-2xl rounded-tl-sm px-3.5 py-2.5'">
                            <p class="text-[12px] leading-[18px]" :class="message.is_mine ? 'text-white' : 'text-primary dark:text-white'" x-text="message.body" style="white-space: pre-wrap;"></p>
                        </div>
                        <p class="text-[10px] text-muted dark:text-[#b4b6c0] mt-1" :class="message.is_mine ? 'text-end' : ''">
                            <span x-text="message.author"></span> · <span x-text="message.when"></span>
                        </p>
                    </div>
                </div>
            </template>
            <p x-show="messages.length === 0" class="text-[11px] text-muted dark:text-[#b4b6c0] italic text-center py-4">
                {{ __('contracts.negotiation_no_messages') }}
            </p>
        </div>

        @if($amendment['can_message'])
            <form
                method="POST"
                action="{{ route('dashboard.contracts.amendments.messages.store', ['id' => $contract_id, 'amendmentId' => $amendment['id']]) }}"
                class="flex items-end gap-2"
            >
                @csrf
                <textarea
                    name="body"
                    rows="2"
                    maxlength="2000"
                    required
                    placeholder="{{ __('contracts.negotiation_message_placeholder') }}"
                    aria-label="{{ __('contracts.negotiation_message_placeholder') }}"
                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
                    class="flex-1 bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-xl px-3 py-2 text-[13px] text-primary dark:text-white placeholder:text-faint dark:placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-accent/50 resize-none"
                ></textarea>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-xl text-[12px] font-bold text-white bg-accent hover:bg-accent-h flex-shrink-0"
                    aria-label="{{ __('contracts.negotiation_send') }}"
                >
                    {{ __('contracts.negotiation_send') }}
                    <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5"/></svg>
                </button>
            </form>
        @endif
    </div>
</div>
