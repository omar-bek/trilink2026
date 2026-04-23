@extends('layouts.dashboard', ['active' => 'reconciliation'])
@section('title', __('recon.statement'))

@section('content')

<a href="{{ route('dashboard.reconciliation.index') }}" class="inline-flex items-center gap-2 text-[13px] text-muted hover:text-primary mb-4">
    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
    {{ __('common.back') }}
</a>

<div class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
    <h1 class="text-[20px] font-bold text-primary mb-2">{{ $statement->statement_date->format('d M Y') }}</h1>
    <p class="text-[12px] font-mono text-muted">{{ $statement->account_identifier }} · {{ $statement->format }}</p>
    <div class="grid grid-cols-3 gap-4 mt-4">
        <div>
            <p class="text-[10px] text-muted uppercase">{{ __('recon.opening_balance') }}</p>
            <p class="text-[16px] font-bold text-primary">{{ $statement->currency }} {{ number_format((float) $statement->opening_balance, 2) }}</p>
        </div>
        <div>
            <p class="text-[10px] text-muted uppercase">{{ __('recon.closing_balance') }}</p>
            <p class="text-[16px] font-bold text-primary">{{ $statement->currency }} {{ number_format((float) $statement->closing_balance, 2) }}</p>
        </div>
        <div>
            <p class="text-[10px] text-muted uppercase">{{ __('recon.delta') }}</p>
            <p class="text-[16px] font-bold {{ $statement->closing_balance > $statement->opening_balance ? 'text-[#00d9b5]' : 'text-[#ff4d7f]' }}">
                {{ $statement->currency }} {{ number_format((float) $statement->closing_balance - (float) $statement->opening_balance, 2) }}
            </p>
        </div>
    </div>
</div>

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('recon.lines') }} ({{ $statement->lines->count() }})</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b border-th-border text-muted">
                    <th class="pb-2">{{ __('recon.value_date') }}</th>
                    <th class="pb-2">{{ __('recon.counterparty') }}</th>
                    <th class="pb-2">{{ __('recon.reference') }}</th>
                    <th class="pb-2 text-right">{{ __('recon.amount') }}</th>
                    <th class="pb-2 text-center">{{ __('recon.match_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statement->lines as $line)
                    @php
                        $statusColor = match($line->match_status) {
                            'matched' => 'bg-[#00d9b5]/10 text-[#00d9b5]',
                            'manual' => 'bg-[#4f7cff]/10 text-[#4f7cff]',
                            'disputed' => 'bg-[#ff4d7f]/10 text-[#ff4d7f]',
                            default => 'bg-[#ffb020]/10 text-[#ffb020]',
                        };
                    @endphp
                    <tr class="border-b border-th-border/50">
                        <td class="py-3 font-mono text-primary">{{ $line->value_date?->format('d M Y') }}</td>
                        <td class="py-3 text-primary">{{ $line->counterparty_name ?? '—' }}</td>
                        <td class="py-3 font-mono text-muted">{{ $line->reference ?? '—' }}</td>
                        <td class="py-3 text-right font-bold {{ $line->direction === 'credit' ? 'text-[#00d9b5]' : 'text-[#ff4d7f]' }}">
                            {{ $line->direction === 'credit' ? '+' : '−' }}{{ $line->currency }} {{ number_format((float) $line->amount, 2) }}
                        </td>
                        <td class="py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $statusColor }}">
                                {{ __('recon.match.' . $line->match_status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
