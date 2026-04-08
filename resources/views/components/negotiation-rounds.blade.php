@props(['bid', 'data'])

{{--
    Structured negotiation rounds tab.

    Renders:
      - timeline of all messages (text + counter offers)
      - if a round is open and the current user is on the responding side,
        show "Accept / Reject / Counter" actions
      - otherwise show a "Open new counter" form

    `$data` is the array returned by BidController::negotiationViewModel().
    `$bid` is the bid view-model array (we only need numeric_id + currency).
--}}

<div x-data="{ counter: false, reject: false }" class="space-y-6">

    {{-- Header strip --}}
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h3 class="text-[18px] font-bold text-primary">{{ __('negotiation.title') }}</h3>
            <p class="text-[12px] text-muted mt-1">
                @if($data['has_open'])
                    {{ __('negotiation.round_n_open', ['n' => $data['open_round']]) }} —
                    <span class="font-semibold text-primary">{{ $data['open_amount'] }}</span>
                @else
                    {{ __('negotiation.no_open_round') }}
                @endif
            </p>
        </div>

        @if(! $data['has_open'])
        <button type="button" @click="counter = ! counter"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('negotiation.start_round') }}
        </button>
        @endif
    </div>

    {{-- Timeline --}}
    <div class="space-y-4">
        @forelse($data['timeline'] as $msg)
            @php
                $isOffer    = $msg['kind'] === 'counter_offer';
                $isBuyer    = $msg['side'] === 'buyer';
                $statusKey  = $msg['round_status'];
                $bubbleSide = $isBuyer ? 'rtl:items-end ltr:items-start' : 'rtl:items-start ltr:items-end';
            @endphp

            <div class="flex flex-col {{ $bubbleSide }}">
                <div class="max-w-[640px] w-full bg-page border border-th-border rounded-2xl p-4">
                    <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide
                                {{ $isBuyer ? 'text-accent bg-accent/10 border border-accent/20' : 'text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20' }}">
                                {{ $isBuyer ? __('negotiation.buyer_team') : __('common.supplier') }}
                            </span>
                            @if($isOffer)
                                <span class="text-[11px] font-semibold text-muted">
                                    {{ __('negotiation.round_n', ['n' => $msg['round']]) }}
                                </span>
                                @if($statusKey === 'open')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20">{{ __('negotiation.status_open') }}</span>
                                @elseif($statusKey === 'accepted')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20">{{ __('negotiation.status_accepted') }}</span>
                                @elseif($statusKey === 'rejected')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20">{{ __('negotiation.status_rejected') }}</span>
                                @elseif($statusKey === 'countered')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-muted bg-surface border border-th-border">{{ __('negotiation.status_countered') }}</span>
                                @endif
                            @endif
                        </div>
                        <span class="text-[11px] text-faint">{{ $msg['when'] }}</span>
                    </div>

                    <p class="text-[13px] text-muted mb-2">{{ $msg['sender'] }}</p>

                    @if($isOffer && $msg['offer'])
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                            <div class="bg-surface border border-th-border rounded-lg p-3">
                                <p class="text-[10px] uppercase font-semibold text-muted mb-1">{{ __('negotiation.amount') }}</p>
                                <p class="text-[15px] font-bold text-accent">{{ $msg['offer']['amount'] }}</p>
                            </div>
                            @if(! is_null($msg['offer']['delivery_days']))
                            <div class="bg-surface border border-th-border rounded-lg p-3">
                                <p class="text-[10px] uppercase font-semibold text-muted mb-1">{{ __('negotiation.delivery') }}</p>
                                <p class="text-[14px] font-bold text-primary">{{ $msg['offer']['delivery_days'] }} {{ __('common.days') }}</p>
                            </div>
                            @endif
                            @if($msg['offer']['payment_terms'])
                            <div class="bg-surface border border-th-border rounded-lg p-3 col-span-2">
                                <p class="text-[10px] uppercase font-semibold text-muted mb-1">{{ __('negotiation.payment_terms') }}</p>
                                <p class="text-[12px] font-semibold text-primary">{{ $msg['offer']['payment_terms'] }}</p>
                            </div>
                            @endif
                        </div>
                    @endif

                    @if(! empty($msg['body']))
                        <p class="text-[13px] text-body leading-relaxed whitespace-pre-line">{{ $msg['body'] }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-page border border-th-border rounded-2xl p-10 text-center">
                <div class="w-12 h-12 rounded-xl bg-surface border border-th-border mx-auto mb-3 flex items-center justify-center text-muted">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-[14px] font-semibold text-primary">{{ __('negotiation.empty_title') }}</p>
                <p class="text-[12px] text-muted mt-1">{{ __('negotiation.empty_subtitle') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Action bar — visible only when there is an open round and current
         user is on the responding side. --}}
    @if($data['has_open'] && $data['can_act'])
        <div class="bg-page border border-th-border rounded-2xl p-5">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
                <div>
                    <p class="text-[13px] font-bold text-primary">{{ __('negotiation.respond_round', ['n' => $data['open_round']]) }}</p>
                    <p class="text-[12px] text-muted mt-1">{{ __('negotiation.respond_hint') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap" x-show="! counter && ! reject">
                <form method="POST" action="{{ route('dashboard.negotiation.accept', ['bid' => $bid['numeric_id']]) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        {{ __('negotiation.accept_offer') }}
                    </button>
                </form>

                <button type="button" @click="counter = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 17l4-4-4-4M8 7l-4 4 4 4M14 4l-4 16"/></svg>
                    {{ __('negotiation.counter_back') }}
                </button>

                <button type="button" @click="reject = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    {{ __('negotiation.reject_offer') }}
                </button>
            </div>

            {{-- Counter form --}}
            <form x-show="counter" x-cloak method="POST" action="{{ route('dashboard.negotiation.counter', ['bid' => $bid['numeric_id']]) }}" class="space-y-3 mt-2">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.new_price') }}</label>
                        <input type="number" name="amount" required step="0.01" min="0" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('negotiation.enter_amount') }}">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.delivery_time') }}</label>
                        <input type="number" name="delivery_days" min="1" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('common.days') }}">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.payment_terms') }}</label>
                        <input type="text" name="payment_terms" maxlength="500" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="e.g. 30/50/20">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.reason_optional') }}</label>
                    <textarea name="reason" rows="2" maxlength="1000" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('negotiation.explain_counter') }}"></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                        {{ __('negotiation.submit_counter') }}
                    </button>
                    <button type="button" @click="counter = false" class="text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
                </div>
            </form>

            {{-- Reject form --}}
            <form x-show="reject" x-cloak method="POST" action="{{ route('dashboard.negotiation.reject', ['bid' => $bid['numeric_id']]) }}" class="space-y-3 mt-2">
                @csrf
                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.reject_reason') }}</label>
                    <textarea name="reason" rows="3" maxlength="500" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('negotiation.reject_reason_placeholder') }}"></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#e64372]">
                        {{ __('negotiation.confirm_reject') }}
                    </button>
                    <button type="button" @click="reject = false" class="text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    @elseif($data['has_open'])
        <div class="bg-page border border-th-border rounded-2xl p-5 text-center">
            <p class="text-[13px] text-muted">{{ __('negotiation.waiting_other_side') }}</p>
        </div>
    @endif

    {{-- "Open new round" form when no round is open --}}
    @if(! $data['has_open'])
        <form x-show="counter" x-cloak method="POST" action="{{ route('dashboard.negotiation.counter', ['bid' => $bid['numeric_id']]) }}" class="bg-page border border-th-border rounded-2xl p-5 space-y-3">
            @csrf
            <p class="text-[13px] font-bold text-primary mb-2">{{ __('negotiation.open_round_title', ['n' => $data['next_round']]) }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.new_price') }}</label>
                    <input type="number" name="amount" required step="0.01" min="0" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('negotiation.enter_amount') }}">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.delivery_time') }}</label>
                    <input type="number" name="delivery_days" min="1" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('common.days') }}">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.payment_terms') }}</label>
                    <input type="text" name="payment_terms" maxlength="500" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.reason_optional') }}</label>
                <textarea name="reason" rows="2" maxlength="1000" class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" placeholder="{{ __('negotiation.explain_counter') }}"></textarea>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                    {{ __('negotiation.submit_counter') }}
                </button>
                <button type="button" @click="counter = false" class="text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif
</div>
