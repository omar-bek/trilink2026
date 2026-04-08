{{--
    Phase 3 — Escrow sidebar panel for the contract show page.

    Inputs:
      $escrow      = data array built by ContractController::buildEscrowPanel
      $contract_id = numeric contract id (used by every form action below)

    Two states:
      1. !$escrow['activated']  → "Activate Escrow" CTA only (buyer-only,
                                  hidden when the user lacks permission).
      2.  $escrow['activated']  → balance card + deposit / release / refund
                                  modals + ledger of recent events.
--}}
<div class="bg-surface border border-th-border rounded-2xl p-6"
     x-data="{ openDeposit: false, openRelease: false, openRefund: false }">

    <div class="flex items-center justify-between mb-4">
        <h3 class="text-[15px] font-bold text-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ __('escrow.title') }}
        </h3>
        @if(!empty($escrow['activated']))
        <span @class([
            'text-[10px] font-bold rounded-full px-2 py-0.5 border',
            'text-[#00d9b5] bg-[#00d9b5]/10 border-[#00d9b5]/20' => $escrow['status'] === 'active',
            'text-muted bg-surface-2 border-th-border'           => $escrow['status'] === 'pending',
            'text-[#ffb020] bg-[#ffb020]/10 border-[#ffb020]/20' => $escrow['status'] === 'closed',
            'text-[#f59e0b] bg-[#f59e0b]/10 border-[#f59e0b]/20' => $escrow['status'] === 'refunded',
        ])>{{ __('escrow.status_' . $escrow['status']) }}</span>
        @endif
    </div>

    @if(empty($escrow['activated']))
        {{-- Inactive state — show the activation CTA (buyer-only). --}}
        <p class="text-[12px] text-muted mb-4 leading-relaxed">{{ __('escrow.intro') }}</p>
        @if(!empty($escrow['can_activate']))
        <form method="POST" action="{{ route('dashboard.escrow.activate', ['id' => $contract_id]) }}">
            @csrf
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v8m-4-4h8m5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('escrow.activate') }}
            </button>
        </form>
        @else
        <p class="text-[11px] text-muted italic">{{ __('escrow.activate_buyer_only') }}</p>
        @endif

    @else
        {{-- Active / closed state — show balance + actions. --}}
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-page border border-th-border rounded-xl p-3">
                <p class="text-[10px] text-muted mb-1">{{ __('escrow.held') }}</p>
                <p class="text-[16px] font-bold text-[#00d9b5]">{{ $escrow['available'] }}</p>
            </div>
            <div class="bg-page border border-th-border rounded-xl p-3">
                <p class="text-[10px] text-muted mb-1">{{ __('escrow.released') }}</p>
                <p class="text-[16px] font-bold text-primary">{{ $escrow['released'] }}</p>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between text-[10px] text-muted mb-1">
                <span>{{ __('escrow.deposited_vs_expected') }}</span>
                <span>{{ $escrow['deposited'] }} / {{ $escrow['expected'] }}</span>
            </div>
            <div class="h-1.5 bg-elevated rounded-full overflow-hidden">
                <div class="h-full bg-accent rounded-full" style="width: {{ $escrow['progress'] }}%"></div>
            </div>
        </div>

        <div class="space-y-2">
            @if(!empty($escrow['can_deposit']))
            <button type="button" @click="openDeposit = true"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                {{ __('escrow.deposit') }}
            </button>
            @endif
            @if(!empty($escrow['can_release']))
            <button type="button" @click="openRelease = true"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[12px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                {{ __('escrow.manual_release') }}
            </button>
            @endif
            @if(!empty($escrow['can_refund']))
            <button type="button" @click="openRefund = true"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-[12px] font-semibold text-[#f59e0b] bg-[#f59e0b]/10 border border-[#f59e0b]/20 hover:bg-[#f59e0b]/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                {{ __('escrow.refund') }}
            </button>
            @endif
        </div>

        @if(!empty($escrow['external_id']))
        <p class="text-[10px] text-muted mt-3 font-mono truncate">{{ $escrow['bank_partner'] }} · {{ $escrow['external_id'] }}</p>
        @endif

        {{-- Recent ledger events. --}}
        @if(!empty($escrow['recent_events']))
        <div class="mt-5 pt-4 border-t border-th-border">
            <p class="text-[11px] font-bold text-muted uppercase tracking-wide mb-2">{{ __('escrow.recent_events') }}</p>
            <div class="space-y-2">
                @foreach($escrow['recent_events'] as $event)
                <div class="flex items-start gap-2 text-[11px]">
                    @php
                        $eventColor = match($event['type']) {
                            'deposit' => 'text-[#00d9b5]',
                            'release' => 'text-accent',
                            'refund'  => 'text-[#f59e0b]',
                            default   => 'text-muted',
                        };
                        $sign = $event['type'] === 'deposit' ? '+' : '−';
                    @endphp
                    <span class="{{ $eventColor }} font-bold">{{ $sign }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-primary font-semibold truncate">{{ $event['amount'] }}</p>
                            <p class="text-muted text-[10px] flex-shrink-0">{{ $event['when'] }}</p>
                        </div>
                        <p class="text-muted text-[10px]">{{ __('escrow.trigger_' . $event['trigger']) }}@if($event['milestone']) · {{ $event['milestone'] }}@endif</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Deposit modal --}}
        <div x-show="openDeposit" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             @click.self="openDeposit = false">
            <div class="bg-surface border border-th-border rounded-2xl w-full max-w-md p-6">
                <h4 class="text-[16px] font-bold text-primary mb-1">{{ __('escrow.deposit_title') }}</h4>
                <p class="text-[12px] text-muted mb-4">{{ __('escrow.deposit_intro') }}</p>
                <form method="POST" action="{{ route('dashboard.escrow.deposit', ['id' => $contract_id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.amount') }} ({{ $escrow['currency'] }})</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required
                               class="w-full px-3 py-2.5 bg-page border border-th-border rounded-lg text-[13px] text-primary"/>
                    </div>
                    <input type="hidden" name="currency" value="{{ $escrow['currency'] }}"/>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="openDeposit = false" class="px-4 py-2 text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-5 py-2.5 rounded-lg text-[12px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('escrow.deposit') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Release modal --}}
        <div x-show="openRelease" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             @click.self="openRelease = false">
            <div class="bg-surface border border-th-border rounded-2xl w-full max-w-md p-6">
                <h4 class="text-[16px] font-bold text-primary mb-1">{{ __('escrow.release_title') }}</h4>
                <p class="text-[12px] text-muted mb-4">{{ __('escrow.release_intro') }}</p>
                <form method="POST" action="{{ route('dashboard.escrow.release', ['id' => $contract_id]) }}" class="space-y-4">
                    @csrf
                    @if(!empty($escrow['unpaid_payments']))
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.against_payment') }}</label>
                        <select name="payment_id" id="escrow-release-payment"
                                onchange="(function(s){var o=s.options[s.selectedIndex];var a=document.getElementById('escrow-release-amount');if(o&&o.dataset.amount){a.value=o.dataset.amount;}})(this)"
                                class="w-full px-3 py-2.5 bg-page border border-th-border rounded-lg text-[13px] text-primary">
                            <option value="">— {{ __('escrow.no_specific_payment') }} —</option>
                            @foreach($escrow['unpaid_payments'] as $p)
                            <option value="{{ $p['id'] }}" data-amount="{{ $p['amount_raw'] }}">{{ $p['milestone'] }} — {{ $p['amount'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.amount') }} ({{ $escrow['currency'] }}) <span class="text-muted">— {{ __('escrow.available') }}: {{ $escrow['available'] }}</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $escrow['available_raw'] }}" name="amount" id="escrow-release-amount" required
                               class="w-full px-3 py-2.5 bg-page border border-th-border rounded-lg text-[13px] text-primary"/>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.notes') }}</label>
                        <textarea name="notes" rows="2" maxlength="500"
                                  class="w-full px-3 py-2 bg-page border border-th-border rounded-lg text-[12px] text-primary"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="openRelease = false" class="px-4 py-2 text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-5 py-2.5 rounded-lg text-[12px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('escrow.release') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Refund modal --}}
        <div x-show="openRefund" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             @click.self="openRefund = false">
            <div class="bg-surface border border-th-border rounded-2xl w-full max-w-md p-6">
                <h4 class="text-[16px] font-bold text-primary mb-1">{{ __('escrow.refund_title') }}</h4>
                <p class="text-[12px] text-muted mb-4">{{ __('escrow.refund_intro') }}</p>
                <form method="POST" action="{{ route('dashboard.escrow.refund', ['id' => $contract_id]) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.amount') }} ({{ $escrow['currency'] }})</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $escrow['available_raw'] }}" name="amount" required
                               class="w-full px-3 py-2.5 bg-page border border-th-border rounded-lg text-[13px] text-primary"/>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-muted mb-1">{{ __('escrow.reason') }}</label>
                        <textarea name="reason" rows="3" maxlength="500" required
                                  class="w-full px-3 py-2 bg-page border border-th-border rounded-lg text-[12px] text-primary"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="openRefund = false" class="px-4 py-2 text-[12px] text-muted hover:text-primary">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-5 py-2.5 rounded-lg text-[12px] font-bold text-white bg-[#f59e0b] hover:bg-[#d18807]">{{ __('escrow.refund') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
