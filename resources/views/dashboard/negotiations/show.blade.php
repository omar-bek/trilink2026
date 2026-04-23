@extends('layouts.dashboard', ['active' => 'bids'])
@section('title', __('negotiation.title'))

@section('content')

<div x-data="{ acceptOpen: false, signerName: '', ack: false, rejectOpen: false }">

{{-- ===== Header ===== --}}
<div class="flex items-start justify-between gap-6 mb-8 flex-wrap">
    <div class="flex items-start gap-4 flex-1 min-w-0">
        <a href="{{ route('dashboard.bids.show', ['id' => $n['numeric_id']]) }}"
           class="w-10 h-10 rounded-xl bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary transition-colors flex-shrink-0">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-3 mb-2 flex-wrap">
                <span class="text-[12px] font-mono text-muted">{{ $n['rfq_number'] }}</span>
                @if($n['is_active'])
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-[#8B5CF6] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#8B5CF6]"></span>
                    {{ __('negotiation.active') }}
                </span>
                @endif
            </div>
            <h1 class="text-[28px] sm:text-[34px] font-bold text-primary leading-tight">{{ __('negotiation.title') }}</h1>
            <p class="text-[14px] text-muted mt-1">{{ $n['rfq_title'] }} · {{ $n['supplier'] }}</p>
        </div>
    </div>

    @if($n['can_act'])
    <div class="flex items-center gap-3 flex-wrap">
        @if($n['can_respond'])
        <button type="button" @click="acceptOpen = true"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] shadow-[0_4px_14px_rgba(0,217,181,0.3)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('negotiation.accept_offer') }}
        </button>
        <button type="button" @click="rejectOpen = true"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            {{ __('negotiation.reject_offer') }}
        </button>
        @endif
        <form method="POST" action="{{ route('dashboard.negotiations.end', ['id' => $n['numeric_id']]) }}"
              onsubmit="return confirm('{{ __('negotiation.end_confirm') }}');">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6m-6 0l6-6"/></svg>
                {{ __('negotiation.end') }}
            </button>
        </form>
    </div>
    @endif
</div>

{{-- Accept (signed) modal — visible when the current user is the
     responder on the open counter round. Uses the signed-acceptance
     flow so both parties have wet-ink-equivalent evidence. --}}
@if($n['can_act'] && $n['can_respond'])
<div x-show="acceptOpen" x-cloak class="mb-6 rounded-2xl border border-[#00d9b5]/30 bg-[#00d9b5]/5 p-5 space-y-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-[14px] font-bold text-primary">{{ __('negotiation.accept_review_title') }}</p>
        <button type="button" @click="acceptOpen = false; ack = false; signerName = ''" class="text-muted hover:text-primary text-[18px] leading-none">&times;</button>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-surface border border-th-border rounded-lg p-3">
        <div>
            <p class="text-[10px] text-muted uppercase">{{ __('negotiation.amount') }}</p>
            <p class="text-[13px] font-bold text-primary">{{ $n['current']['amount'] }}</p>
        </div>
        <div>
            <p class="text-[10px] text-muted uppercase">{{ __('negotiation.delivery') }}</p>
            <p class="text-[13px] font-bold text-primary">{{ $n['current']['delivery_days'] }} {{ __('common.days') }}</p>
        </div>
        <div class="col-span-2 sm:col-span-1">
            <p class="text-[10px] text-muted uppercase">{{ __('negotiation.payment_terms') }}</p>
            <p class="text-[13px] font-bold text-primary">{{ $n['current']['terms'] }}</p>
        </div>
    </div>
    <form method="POST" action="{{ route('dashboard.negotiations.accept', ['id' => $n['numeric_id']]) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.signature_name_label') }}</label>
            <input type="text" name="signature_name" x-model="signerName" required minlength="3" maxlength="150"
                   class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary"
                   placeholder="{{ __('negotiation.signature_name_placeholder') }}">
            <p class="text-[10px] text-muted mt-1">{{ __('negotiation.signature_hint') }}</p>
        </div>
        <label class="flex items-start gap-2 text-[12px] text-primary cursor-pointer">
            <input type="checkbox" name="acknowledge" value="1" x-model="ack" required class="mt-0.5">
            <span>{{ __('negotiation.accept_ack') }}</span>
        </label>
        <div class="flex items-center gap-3">
            <button type="submit" :disabled="! ack || signerName.length < 3"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] disabled:opacity-40 disabled:cursor-not-allowed">
                {{ __('negotiation.sign_and_accept') }}
            </button>
            <button type="button" @click="acceptOpen = false; ack = false; signerName = ''" class="text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
        </div>
    </form>
</div>

{{-- Reject round form. Uses the structured round reject endpoint so
     the round is marked REJECTED in the negotiation trail; the bid
     itself stays open for a new round or a final "End Negotiation". --}}
<div x-show="rejectOpen" x-cloak class="mb-6 rounded-2xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/5 p-5 space-y-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-[14px] font-bold text-primary">{{ __('negotiation.reject_offer') }} — {{ __('negotiation.round_n', ['n' => $n['open_round_number']]) }}</p>
        <button type="button" @click="rejectOpen = false" class="text-muted hover:text-primary text-[18px] leading-none">&times;</button>
    </div>
    <form method="POST" action="{{ route('dashboard.negotiation.reject', ['bid' => $n['numeric_id']]) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-[11px] font-semibold text-muted uppercase tracking-wide mb-1">{{ __('negotiation.reject_reason') }}</label>
            <textarea name="reason" rows="3" maxlength="500"
                      class="w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary"
                      placeholder="{{ __('negotiation.reject_reason_placeholder') }}"></textarea>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#e64372]">
                {{ __('negotiation.confirm_reject') }}
            </button>
            <button type="button" @click="rejectOpen = false" class="text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
        </div>
    </form>
</div>
@endif

@if(session('status'))
<div class="mb-6 rounded-xl border border-[#00d9b5]/30 bg-[#00d9b5]/10 text-[#00d9b5] px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-6 rounded-xl border border-[#ff4d7f]/30 bg-[#ff4d7f]/10 text-[#ff4d7f] px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

{{-- ===== Main 2-col grid ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ==== Chat panel ==== --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl flex flex-col min-h-[640px]">

        {{-- Message list --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-5" id="negotiation-messages">
            @forelse($n['messages'] as $msg)
            @php $mine = $msg['mine']; @endphp

            <div class="flex items-start gap-3 {{ $mine ? 'flex-row-reverse' : '' }}">
                <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 {{ $mine ? 'bg-accent text-white' : 'bg-[#00d9b5]/15 text-[#00d9b5]' }}">
                    @if($mine)
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                    @else
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    @endif
                </div>

                <div class="flex-1 min-w-0 {{ $mine ? 'text-end' : '' }}">
                    <div class="flex items-center gap-2 mb-1 text-[12px] text-muted {{ $mine ? 'justify-end' : '' }}">
                        <span class="font-semibold text-primary">{{ $msg['author'] }}</span>
                        <span class="text-faint">{{ $msg['time'] }}</span>
                    </div>

                    @if($msg['kind'] === 'counter_offer' && $msg['offer'])
                    <div class="inline-block w-full max-w-[480px] text-start bg-[#8B5CF6]/10 border border-[#8B5CF6]/30 rounded-2xl p-5">
                        <div class="flex items-center gap-2 text-[#8B5CF6] text-[13px] font-semibold mb-3">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                            {{ __('negotiation.counter_offer') }}
                        </div>
                        <dl class="space-y-2 text-[13px]">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">{{ __('negotiation.amount') }}:</dt>
                                <dd class="font-bold text-accent text-[16px]">{{ $msg['offer']['amount'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">{{ __('negotiation.delivery') }}:</dt>
                                <dd class="font-semibold text-primary">{{ $msg['offer']['delivery_days'] }} {{ __('common.days') }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">{{ __('negotiation.payment') }}:</dt>
                                <dd class="font-semibold text-primary text-end">{{ $msg['offer']['payment_terms'] }}</dd>
                            </div>
                        </dl>
                        @if(!empty($msg['offer']['reason']))
                        <p class="text-[13px] text-muted leading-relaxed mt-4 pt-3 border-t border-[#8B5CF6]/20">{{ $msg['offer']['reason'] }}</p>
                        @endif
                    </div>
                    @else
                    <div class="inline-block max-w-[480px] text-start rounded-2xl px-4 py-3 text-[13px] leading-relaxed {{ $mine ? 'bg-accent text-white' : 'bg-page border border-th-border text-body' }}">
                        {{ $msg['body'] }}
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-14 h-14 rounded-full bg-accent/10 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-[14px] font-semibold text-primary mb-1">{{ __('negotiation.empty_title') }}</p>
                <p class="text-[12px] text-muted">{{ __('negotiation.empty_subtitle') }}</p>
            </div>
            @endforelse
        </div>

        {{-- Message input --}}
        @if($n['can_act'])
        <form method="POST" action="{{ route('dashboard.negotiations.message', ['id' => $n['numeric_id']]) }}"
              class="border-t border-th-border p-4 flex items-end gap-3">
            @csrf
            <textarea name="body" rows="2" required
                      placeholder="{{ __('negotiation.type_message') }}"
                      class="flex-1 bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none"></textarea>
            <button type="submit" class="w-11 h-11 rounded-xl bg-accent hover:bg-accent-h text-white flex items-center justify-center flex-shrink-0" title="{{ __('negotiation.send') }}">
                <svg class="w-5 h-5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
            </button>
        </form>
        @endif
    </div>

    {{-- ==== Right sidebar ==== --}}
    <div class="space-y-6">

        {{-- Current Offer --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-4">{{ __('negotiation.current_offer') }}</h3>

            <p class="text-[11px] text-muted mb-1">{{ __('negotiation.total_amount') }}</p>
            <p class="text-[28px] font-bold text-accent leading-none mb-2">{{ $n['current']['amount'] }}</p>
            <p class="text-[12px] font-semibold mb-5 inline-flex items-center gap-1.5 {{ $n['current']['diff_positive'] ? 'text-[#00d9b5]' : 'text-[#ff4d7f]' }}">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                    @if($n['current']['diff_positive'])
                    <path d="M12 19l-8-8h16z"/>
                    @else
                    <path d="M12 5l8 8H4z"/>
                    @endif
                </svg>
                {{ $n['current']['diff_label'] }}
            </p>

            <div class="space-y-4 pt-4 border-t border-th-border text-[13px]">
                <div>
                    <p class="text-[11px] text-muted mb-0.5">{{ __('negotiation.delivery_time') }}</p>
                    <p class="font-semibold text-primary">{{ $n['current']['delivery_days'] }} {{ __('common.days') }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted mb-0.5">{{ __('negotiation.payment_terms') }}</p>
                    <p class="font-semibold text-primary">{{ $n['current']['terms'] }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-muted mb-0.5">{{ __('negotiation.valid_until') }}</p>
                    <p class="font-semibold text-primary inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        {{ $n['current']['valid_until'] }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Price Analysis --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-4">{{ __('negotiation.price_analysis') }}</h3>
            <div class="space-y-3">
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[12px] text-muted">{{ __('negotiation.original_budget') }}</span>
                    <span class="text-[14px] font-bold text-primary">{{ $n['analysis']['original_budget'] }}</span>
                </div>
                <div class="bg-accent/5 border border-accent/30 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[12px] text-muted">{{ __('negotiation.current_offer') }}</span>
                    <span class="text-[14px] font-bold text-accent">{{ $n['analysis']['current_offer'] }}</span>
                </div>
                <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-[12px] text-muted">{{ __('negotiation.target_price') }}</span>
                    <span class="text-[14px] font-bold text-[#00d9b5]">{{ $n['analysis']['target_price'] }}</span>
                </div>
            </div>
        </div>

        {{-- Submit Counter Offer --}}
        @if($n['can_act'])
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-4">{{ __('negotiation.submit_counter') }}</h3>
            <form method="POST" action="{{ route('dashboard.negotiations.counter', ['id' => $n['numeric_id']]) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('negotiation.new_price') }} ({{ __('common.amount') }})</label>
                    <input type="number" name="amount" min="1" step="0.01" required
                           value="{{ old('amount', $n['current']['amount_raw']) }}"
                           placeholder="{{ __('negotiation.enter_amount') }}"
                           class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50" />
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('negotiation.delivery_time') }} ({{ __('common.days') }})</label>
                    <input type="number" name="delivery_days" min="1" max="3650" required
                           value="{{ old('delivery_days', $n['current']['delivery_days']) }}"
                           class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/50" />
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('negotiation.payment_terms') }}</label>
                    <input type="text" name="payment_terms" required maxlength="255"
                           value="{{ old('payment_terms', $n['current']['terms']) }}"
                           class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary focus:outline-none focus:border-accent/50" />
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-primary mb-1.5">{{ __('negotiation.reason_optional') }}</label>
                    <textarea name="reason" rows="3" maxlength="1000"
                              placeholder="{{ __('negotiation.explain_counter') }}"
                              class="w-full bg-page border border-th-border rounded-xl px-4 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none">{{ old('reason') }}</textarea>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-[13px] font-semibold text-white bg-[#8B5CF6] hover:bg-[#7c4dea] shadow-[0_4px_14px_rgba(139,92,246,0.3)]">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    {{ __('negotiation.submit_counter') }}
                </button>
            </form>
        </div>
        @endif

        {{-- Negotiation Tips --}}
        <div class="bg-accent/5 border border-accent/20 rounded-2xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                <h4 class="text-[14px] font-bold text-primary">{{ __('negotiation.tips_title') }}</h4>
            </div>
            <ul class="space-y-1.5 text-[12px] text-muted">
                <li>• {{ __('negotiation.tip_1') }}</li>
                <li>• {{ __('negotiation.tip_2') }}</li>
                <li>• {{ __('negotiation.tip_3') }}</li>
                <li>• {{ __('negotiation.tip_4') }}</li>
            </ul>
        </div>
    </div>
</div>

</div>

@push('scripts')
<script>
// Auto-scroll the message list to the newest message so the room feels alive.
(function () {
    const list = document.getElementById('negotiation-messages');
    if (list) list.scrollTop = list.scrollHeight;
})();
</script>
@endpush

@endsection
