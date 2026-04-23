@extends('layouts.dashboard', ['active' => 'cheques'])

@section('content')
<div class="px-6 py-6 max-w-[1280px] mx-auto space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-[22px] font-bold text-primary">{{ __('cheques.title') }}</h1>
        <a href="#new-cheque"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
            {{ __('cheques.new') }}
        </a>
    </div>

    @if(session('status'))
        <div class="bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] rounded-xl px-4 py-3 text-[13px]">{{ session('status') }}</div>
    @endif
    @error('cheque')
        <div class="bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] rounded-xl px-4 py-3 text-[13px]">{{ $message }}</div>
    @enderror

    <div class="flex items-center gap-2 flex-wrap text-[12px]">
        <a href="{{ route('dashboard.cheques.index') }}"
           class="px-3 py-1.5 rounded-lg {{ ! request('status') ? 'bg-accent text-white' : 'bg-surface border border-th-border text-muted' }}">
            {{ __('common.all') }}
        </a>
        @foreach($statuses as $s)
            <a href="{{ route('dashboard.cheques.index', ['status' => $s]) }}"
               class="px-3 py-1.5 rounded-lg {{ request('status') === $s ? 'bg-accent text-white' : 'bg-surface border border-th-border text-muted' }}">
                {{ __('cheques.status_'.$s) }}
            </a>
        @endforeach
    </div>

    <div class="bg-page border border-th-border rounded-2xl overflow-hidden">
        <table class="w-full text-[13px]">
            <thead class="bg-surface text-muted text-[11px] uppercase">
                <tr>
                    <th class="text-start px-4 py-3">{{ __('cheques.number') }}</th>
                    <th class="text-start px-4 py-3">{{ __('cheques.beneficiary') }}</th>
                    <th class="text-start px-4 py-3">{{ __('cheques.bank') }}</th>
                    <th class="text-start px-4 py-3">{{ __('cheques.presentation_date') }}</th>
                    <th class="text-end px-4 py-3">{{ __('cheques.amount') }}</th>
                    <th class="text-start px-4 py-3">{{ __('common.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cheques as $c)
                    <tr class="border-t border-th-border hover:bg-surface/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('dashboard.cheques.show', ['id' => $c->id]) }}"
                               class="text-accent hover:underline font-semibold">{{ $c->cheque_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-primary">{{ $c->beneficiary?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $c->drawer_bank_name }}</td>
                        <td class="px-4 py-3 text-muted">{{ optional($c->presentation_date)->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-end font-semibold text-primary">
                            {{ number_format((float) $c->amount, 2) }} {{ $c->currency }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-surface border border-th-border text-muted">
                                {{ __('cheques.status_'.($c->status?->value ?? 'issued')) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-muted">{{ __('cheques.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cheques->links() }}</div>

    {{-- Register new cheque --}}
    <form id="new-cheque" method="POST" action="{{ route('dashboard.cheques.store') }}"
          class="bg-page border border-th-border rounded-2xl p-5 space-y-4">
        @csrf
        <h2 class="text-[16px] font-bold text-primary">{{ __('cheques.register_title') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.number') }}</span>
                <input type="text" name="cheque_number" required maxlength="32"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.beneficiary_company_id') }}</span>
                <input type="number" name="beneficiary_company_id" required
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.bank') }}</span>
                <input type="text" name="drawer_bank_name" required maxlength="150"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.iban') }}</span>
                <input type="text" name="drawer_account_iban" maxlength="34"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.issue_date') }}</span>
                <input type="date" name="issue_date" required
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.presentation_date') }}</span>
                <input type="date" name="presentation_date" required
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.amount') }}</span>
                <input type="number" name="amount" required step="0.01" min="0.01"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.currency') }}</span>
                <input type="text" name="currency" value="AED" maxlength="3"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary uppercase">
            </label>
            <label class="block">
                <span class="text-[11px] font-semibold text-muted uppercase">{{ __('cheques.payment_id_optional') }}</span>
                <input type="number" name="payment_id"
                       class="mt-1 w-full bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
            </label>
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
            {{ __('cheques.register') }}
        </button>
    </form>
</div>
@endsection
