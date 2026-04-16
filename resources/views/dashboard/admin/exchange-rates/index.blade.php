@extends('layouts.dashboard', ['active' => 'admin-exchange-rates'])
@section('title', __('admin.exchange_rates.title'))

@section('content')

<x-admin.navbar active="exchange-rates" />

<div class="mb-6">
    <h2 class="text-[20px] font-bold text-primary">{{ __('admin.exchange_rates.title') }}</h2>
    <p class="text-[13px] text-muted mt-1">{{ __('admin.exchange_rates.subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">{{ session('status') }}</div>
@endif

{{-- Add form --}}
<form method="POST" action="{{ route('admin.exchange-rates.store') }}" class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    @csrf
    <h3 class="text-[14px] font-bold text-primary mb-4">{{ __('admin.exchange_rates.add') }}</h3>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <input name="from_currency" required placeholder="USD" maxlength="3" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted uppercase" />
        <input name="to_currency" required placeholder="AED" maxlength="3" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted uppercase" />
        <input name="rate" type="number" step="0.00000001" required placeholder="3.6725" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
        <input name="as_of" type="date" required class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary" />
        <button type="submit" class="h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('admin.exchange_rates.add') }}</button>
    </div>
    <input name="source" placeholder="{{ __('admin.exchange_rates.source') }}" class="mt-3 w-full bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
</form>

{{-- Rates table --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full text-[13px]">
        <thead>
            <tr class="border-b border-th-border bg-surface-2">
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.exchange_rates.pair') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.exchange_rates.rate') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.exchange_rates.date') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.exchange_rates.source') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rates as $r)
            <tr class="border-b border-th-border/50 hover:bg-surface-2">
                <td class="py-3 px-4 font-semibold text-primary">{{ $r->from_currency }}/{{ $r->to_currency }}</td>
                <td class="py-3 px-4 text-center font-mono text-primary">{{ number_format($r->rate, 6) }}</td>
                <td class="py-3 px-4 text-center text-muted">{{ $r->as_of?->format('d M Y') ?? '—' }}</td>
                <td class="py-3 px-4 text-center text-muted">{{ $r->source ?? '—' }}</td>
                <td class="py-3 px-4 text-right">
                    <form method="POST" action="{{ route('admin.exchange-rates.destroy', $r->id) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[11px] text-[#ff4d7f] hover:underline">{{ __('common.delete') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="py-12 text-center text-[14px] text-muted">{{ __('admin.exchange_rates.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $rates->links() }}</div>

@endsection
