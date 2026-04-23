@extends('layouts.dashboard', ['active' => 'bank-guarantees'])
@section('title', $bg->bg_number)

@section('content')

<div class="mb-6">
    <a href="{{ route('dashboard.bank-guarantees.index') }}" class="inline-flex items-center gap-2 text-[13px] text-muted hover:text-primary mb-3">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('common.back') }}
    </a>

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <p class="text-[12px] font-mono text-muted">{{ $bg->bg_number }}</p>
            <h1 class="text-[24px] sm:text-[30px] font-bold text-primary leading-tight">{{ __('bg.type.' . $bg->type?->value) }}</h1>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border
                    @if(in_array($bg->status?->value, ['live','issued','reduced'], true)) bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/20
                    @elseif($bg->status?->value === 'called') bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/20
                    @else bg-page text-muted border-th-border
                    @endif">{{ __('bg.status.' . $bg->status?->value) }}</span>
                <span class="text-[12px] text-muted">{{ $bg->governing_rules }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if($isBeneficiary && $bg->status?->value === 'pending_issuance')
                <form method="POST" action="{{ route('dashboard.bank-guarantees.activate', ['id' => $bg->id]) }}">
                    @csrf
                    <button class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#4f7cff] hover:bg-[#3d6ae8]">{{ __('bg.activate') }}</button>
                </form>
            @endif
            @if($isBeneficiary && in_array($bg->status?->value, ['live','issued','reduced'], true))
                <button type="button" onclick="document.getElementById('call-modal').classList.remove('hidden')"
                        class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#e83a6c]">{{ __('bg.call') }}</button>
                <form method="POST" action="{{ route('dashboard.bank-guarantees.release', ['id' => $bg->id]) }}">
                    @csrf
                    <button class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/30 hover:bg-[#00d9b5]/15">{{ __('bg.release') }}</button>
                </form>
            @endif
            <button type="button" onclick="document.getElementById('extend-modal').classList.remove('hidden')"
                    class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border">{{ __('bg.extend') }}</button>
        </div>
    </div>
</div>

@if(session('status'))
<div class="mb-4 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 px-4 py-3 text-[13px] text-[#00d9b5] font-medium">{{ session('status') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('bg.details') }}</h3>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.applicant') }}</p>
                    <p class="font-semibold text-primary">{{ $bg->applicant?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.beneficiary') }}</p>
                    <p class="font-semibold text-primary">{{ $bg->beneficiary?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.issuing_bank') }}</p>
                    <p class="font-semibold text-primary">{{ $bg->issuing_bank_name }}</p>
                    @if($bg->issuing_bank_swift)<p class="text-[11px] text-muted font-mono">{{ $bg->issuing_bank_swift }}</p>@endif
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.bank_reference') }}</p>
                    <p class="font-mono text-primary">{{ $bg->issuing_bank_reference ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.amount') }}</p>
                    <p class="text-[20px] font-bold text-primary">{{ $bg->currency }} {{ number_format((float) $bg->amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.remaining') }}</p>
                    <p class="text-[16px] font-bold text-[#00d9b5]">{{ $bg->currency }} {{ number_format($bg->remainingLiability(), 2) }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.validity_start') }}</p>
                    <p class="font-semibold text-primary">{{ $bg->validity_start_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-muted uppercase">{{ __('bg.expiry') }}</p>
                    <p class="font-semibold text-primary">{{ $bg->expiry_date?->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        @if($bg->calls->isNotEmpty())
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('bg.calls_history') }}</h3>
            <div class="space-y-3">
                @foreach($bg->calls as $call)
                    <div class="border border-th-border rounded-xl p-4">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <p class="text-[18px] font-bold text-primary">{{ $call->currency }} {{ number_format((float) $call->amount, 2) }}</p>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-[#ff4d7f]/10 text-[#ff4d7f]">{{ $call->status }}</span>
                        </div>
                        <p class="text-[12px] text-body">{{ $call->reason }}</p>
                        <p class="text-[10px] text-muted mt-2">{{ $call->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <h3 class="text-[13px] font-bold text-primary mb-3 uppercase tracking-wider">{{ __('bg.timeline') }}</h3>
            <ol class="relative border-s-2 border-th-border space-y-3 ms-1">
                @foreach($bg->events as $e)
                    <li class="ms-4">
                        <div class="absolute w-2 h-2 bg-accent rounded-full -start-[5px] mt-1.5"></div>
                        <p class="text-[12px] font-semibold text-primary">{{ __('bg.event.' . $e->event) }}</p>
                        <p class="text-[10px] text-faint">{{ $e->created_at->format('d M Y H:i') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</div>

<div id="call-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl w-full max-w-md">
        <div class="p-5 border-b border-th-border">
            <h3 class="text-[16px] font-bold text-primary">{{ __('bg.call_action') }}</h3>
            <p class="text-[12px] text-muted mt-1">{{ __('bg.call_warning') }}</p>
        </div>
        <form method="POST" action="{{ route('dashboard.bank-guarantees.call', ['id' => $bg->id]) }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.amount') }}</label>
                <input type="number" name="amount" required step="0.01" min="0" max="{{ $bg->remainingLiability() }}" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.reason') }}</label>
                <textarea name="reason" rows="4" required maxlength="2000" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('call-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-[#ff4d7f]">{{ __('bg.confirm_call') }}</button>
            </div>
        </form>
    </div>
</div>

<div id="extend-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl w-full max-w-md">
        <div class="p-5 border-b border-th-border">
            <h3 class="text-[16px] font-bold text-primary">{{ __('bg.extend') }}</h3>
        </div>
        <form method="POST" action="{{ route('dashboard.bank-guarantees.extend', ['id' => $bg->id]) }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.new_expiry') }}</label>
                <input type="date" name="new_expiry" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.extension_note') }}</label>
                <textarea name="note" rows="3" maxlength="500" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('extend-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-[12px] bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent">{{ __('bg.confirm_extend') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection
