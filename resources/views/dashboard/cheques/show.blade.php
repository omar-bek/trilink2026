@extends('layouts.dashboard', ['active' => 'cheques'])

@section('content')
<div class="px-6 py-6 max-w-[960px] mx-auto space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-[22px] font-bold text-primary">
                {{ __('cheques.cheque_n', ['n' => $cheque->cheque_number]) }}
            </h1>
            <p class="text-[12px] text-muted mt-1">
                {{ $cheque->drawer_bank_name }} • {{ $cheque->currency }} {{ number_format((float) $cheque->amount, 2) }}
            </p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide bg-surface border border-th-border text-muted">
            {{ __('cheques.status_'.($cheque->status?->value ?? 'issued')) }}
        </span>
    </div>

    @if(session('status'))
        <div class="bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] rounded-xl px-4 py-3 text-[13px]">{{ session('status') }}</div>
    @endif
    @error('cheque')
        <div class="bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] rounded-xl px-4 py-3 text-[13px]">{{ $message }}</div>
    @enderror

    <div class="bg-page border border-th-border rounded-2xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-[13px]">
        <div><span class="text-muted">{{ __('cheques.issuer') }}:</span> <span class="text-primary font-semibold">{{ $cheque->issuer?->name ?? '—' }}</span></div>
        <div><span class="text-muted">{{ __('cheques.beneficiary') }}:</span> <span class="text-primary font-semibold">{{ $cheque->beneficiary?->name ?? '—' }}</span></div>
        <div><span class="text-muted">{{ __('cheques.issue_date') }}:</span> <span class="text-primary">{{ optional($cheque->issue_date)->format('M j, Y') }}</span></div>
        <div><span class="text-muted">{{ __('cheques.presentation_date') }}:</span> <span class="text-primary">{{ optional($cheque->presentation_date)->format('M j, Y') }}</span></div>
        <div><span class="text-muted">{{ __('cheques.iban') }}:</span> <span class="text-primary">{{ $cheque->drawer_account_iban ?? '—' }}</span></div>
        <div><span class="text-muted">{{ __('cheques.linked_payment') }}:</span>
            @if($cheque->payment_id)
                <a href="{{ route('dashboard.payments.show', ['id' => $cheque->payment_id]) }}" class="text-accent hover:underline">#{{ $cheque->payment_id }}</a>
            @else — @endif
        </div>
    </div>

    {{-- Transitions --}}
    <div class="bg-page border border-th-border rounded-2xl p-5 space-y-3">
        <h2 class="text-[14px] font-bold text-primary">{{ __('cheques.actions') }}</h2>
        <div class="flex items-center gap-2 flex-wrap">
            @if($cheque->status?->value === 'issued')
                <form method="POST" action="{{ route('dashboard.cheques.deposit', ['id' => $cheque->id]) }}">@csrf
                    <button class="px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('cheques.deposit') }}</button>
                </form>
            @endif
            @if($cheque->status?->value === 'deposited')
                <form method="POST" action="{{ route('dashboard.cheques.clear', ['id' => $cheque->id]) }}">@csrf
                    <button class="px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5]">{{ __('cheques.clear') }}</button>
                </form>
                <form method="POST" action="{{ route('dashboard.cheques.return', ['id' => $cheque->id]) }}" class="flex items-center gap-2">@csrf
                    <input type="text" name="reason" required maxlength="200" placeholder="{{ __('cheques.return_reason_placeholder') }}"
                           class="bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                    <button class="px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#e64372]">{{ __('cheques.return') }}</button>
                </form>
            @endif
            @if(in_array($cheque->status?->value, ['issued', 'deposited'], true))
                <form method="POST" action="{{ route('dashboard.cheques.stop', ['id' => $cheque->id]) }}" class="flex items-center gap-2">@csrf
                    <input type="text" name="reason" required maxlength="200" placeholder="{{ __('cheques.stop_reason_placeholder') }}"
                           class="bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                    <button class="px-4 py-2 rounded-xl text-[13px] font-semibold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/30">{{ __('cheques.stop') }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Event log --}}
    <div class="bg-page border border-th-border rounded-2xl p-5">
        <h2 class="text-[14px] font-bold text-primary mb-3">{{ __('cheques.event_log') }}</h2>
        <ul class="space-y-2 text-[13px]">
            @forelse($cheque->events as $e)
                <li class="flex items-center justify-between border-b border-th-border/50 pb-2">
                    <span class="text-primary font-semibold">{{ __('cheques.event_'.$e->event) }}</span>
                    <span class="text-muted text-[11px]">{{ optional($e->created_at)->format('M j, Y g:i A') }} · {{ $e->actor?->full_name ?? 'system' }}</span>
                </li>
            @empty
                <li class="text-muted text-[12px]">{{ __('cheques.no_events') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
