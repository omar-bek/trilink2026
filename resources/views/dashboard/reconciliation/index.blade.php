@extends('layouts.dashboard', ['active' => 'reconciliation'])
@section('title', __('recon.title'))

@section('content')

<x-dashboard.page-header :title="__('recon.title')" :subtitle="__('recon.subtitle')" />

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <x-dashboard.stat-card :value="number_format($stats['statements'])" :label="__('recon.statements_imported')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['unmatched'])" :label="__('recon.unmatched_queue')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008z"/>' />
    <x-dashboard.stat-card :value="number_format($stats['matched_today'])" :label="__('recon.matched_today')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' />
</div>

{{-- Upload form --}}
<div class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('recon.upload_statement') }}</h3>
    <p class="text-[12px] text-muted mb-4">{{ __('recon.upload_hint') }}</p>
    <form method="POST" action="{{ route('dashboard.reconciliation.upload') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="file" accept=".txt,.mt,.sta,.940" required
               class="text-[12px] file:bg-accent file:text-white file:border-0 file:rounded-lg file:px-4 file:py-2 file:font-semibold file:me-3 text-muted">
        <button type="submit" class="px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('recon.import') }}</button>
    </form>
    @if(session('error'))
        <p class="mt-3 text-[12px] text-[#ff4d7f]">{{ session('error') }}</p>
    @endif
    @if(session('status'))
        <p class="mt-3 text-[12px] text-[#00d9b5]">{{ session('status') }}</p>
    @endif
</div>

{{-- Unmatched queue --}}
@if($unmatched->isNotEmpty())
<div class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('recon.unmatched_lines') }}</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b border-th-border text-muted">
                    <th class="pb-2">{{ __('recon.value_date') }}</th>
                    <th class="pb-2">{{ __('recon.counterparty') }}</th>
                    <th class="pb-2">{{ __('recon.reference') }}</th>
                    <th class="pb-2 text-right">{{ __('recon.amount') }}</th>
                    <th class="pb-2 text-center">{{ __('recon.direction') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unmatched as $line)
                    <tr class="border-b border-th-border/50">
                        <td class="py-3 font-mono text-primary">{{ $line->value_date?->format('d M Y') }}</td>
                        <td class="py-3">
                            <p class="text-primary font-semibold">{{ $line->counterparty_name ?? '—' }}</p>
                            @if($line->counterparty_iban)<p class="text-[10px] text-muted font-mono">{{ $line->counterparty_iban }}</p>@endif
                        </td>
                        <td class="py-3 font-mono text-muted">{{ $line->reference ?? '—' }}</td>
                        <td class="py-3 text-right font-bold {{ $line->direction === 'credit' ? 'text-[#00d9b5]' : 'text-[#ff4d7f]' }}">
                            {{ $line->direction === 'credit' ? '+' : '−' }}{{ $line->currency }} {{ number_format((float) $line->amount, 2) }}
                        </td>
                        <td class="py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                {{ $line->direction === 'credit' ? 'bg-[#00d9b5]/10 text-[#00d9b5]' : 'bg-[#ff4d7f]/10 text-[#ff4d7f]' }}">
                                {{ __('recon.direction.' . $line->direction) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Statements history --}}
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('recon.statements_history') }}</h3>
    @if($statements->isEmpty())
        <p class="text-[13px] text-muted text-center py-6">{{ __('recon.no_statements') }}</p>
    @else
        <div class="space-y-2">
            @foreach($statements as $stmt)
                @php
                    $total = $stmt->lines_count ?: 1;
                    $pct = round(($stmt->matched_count / $total) * 100);
                @endphp
                <a href="{{ route('dashboard.reconciliation.show', ['id' => $stmt->id]) }}"
                   class="flex items-center justify-between gap-4 p-4 rounded-xl border border-th-border hover:border-accent/30">
                    <div>
                        <p class="font-semibold text-primary">{{ $stmt->statement_date->format('d M Y') }} · <span class="font-mono text-[12px] text-muted">{{ $stmt->account_identifier }}</span></p>
                        <p class="text-[11px] text-muted">{{ $stmt->format }} · {{ $stmt->lines_count }} {{ __('recon.lines') }} · {{ $stmt->matched_count }} {{ __('recon.matched') }} ({{ $pct }}%)</p>
                    </div>
                    <p class="text-[14px] font-bold text-primary">{{ $stmt->currency }} {{ number_format((float) $stmt->closing_balance, 2) }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $statements->links() }}</div>
    @endif
</div>

@endsection
