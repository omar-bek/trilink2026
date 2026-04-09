@extends('layouts.dashboard', ['active' => 'admin-tax-invoices'])
@section('title', __('tax_invoices.index_title'))

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('tax_invoices.index_title') }}</h1>
        <p class="text-[16px] text-muted mt-1">{{ __('tax_invoices.index_subtitle') }}</p>
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold text-accent leading-[32px]">{{ $stats['total_issued'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ __('tax_invoices.stat_total_issued') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold text-[#00d9b5] leading-[32px]">{{ $stats['this_month'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ __('tax_invoices.stat_this_month') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold text-[#ffb020] leading-[32px] truncate">{{ $stats['vat_this_month'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ __('tax_invoices.stat_vat_collected') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-[16px] p-[17px]">
        <p class="text-[24px] font-semibold text-[#ff4d7f] leading-[32px]">{{ $stats['total_voided'] }}</p>
        <p class="text-[14px] text-muted leading-[20px] mt-1">{{ __('tax_invoices.stat_voided') }}</p>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <div class="flex-1 relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ $filters['q'] }}"
               placeholder="{{ __('tax_invoices.search_placeholder') }}"
               class="w-full bg-page border border-th-border rounded-[12px] ps-11 pe-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60 transition-colors">
    </div>
    <select name="status" class="bg-page border border-th-border rounded-[12px] px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60">
        <option value="">{{ __('common.all') }}</option>
        <option value="issued" @selected($filters['status'] === 'issued')>{{ __('tax_invoices.status_issued') }}</option>
        <option value="voided" @selected($filters['status'] === 'voided')>{{ __('tax_invoices.status_voided') }}</option>
    </select>
    <select name="year" class="bg-page border border-th-border rounded-[12px] px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60">
        <option value="">{{ __('tax_invoices.all_years') }}</option>
        @for($y = now()->year; $y >= now()->year - 5; $y--)
            <option value="{{ $y }}" @selected((int) $filters['year'] === $y)>{{ $y }}</option>
        @endfor
    </select>
    <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 h-12 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
        {{ __('common.filter') }}
    </button>
</form>

{{-- List --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <table class="w-full">
        <thead class="bg-surface-2/40 border-b border-th-border">
            <tr>
                <th class="text-start px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('tax_invoices.col_number') }}</th>
                <th class="text-start px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('tax_invoices.col_supplier') }}</th>
                <th class="text-start px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('tax_invoices.col_buyer') }}</th>
                <th class="text-start px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('tax_invoices.col_issue_date') }}</th>
                <th class="text-end px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('tax_invoices.col_total') }}</th>
                <th class="text-center px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-end px-5 py-3 text-[12px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
                <tr class="border-b border-th-border hover:bg-page/50 transition-colors">
                    <td class="px-5 py-4">
                        <a href="{{ route('admin.tax-invoices.show', $inv->id) }}" class="text-[13px] font-mono font-semibold text-accent hover:underline">{{ $inv->invoice_number }}</a>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[13px] font-medium text-primary truncate max-w-[200px]">{{ $inv->supplier_name }}</p>
                        @if($inv->supplier_trn)
                            <p class="text-[11px] text-muted font-mono">TRN: {{ $inv->supplier_trn }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[13px] text-primary truncate max-w-[200px]">{{ $inv->buyer_name }}</p>
                        @if($inv->buyer_trn)
                            <p class="text-[11px] text-muted font-mono">TRN: {{ $inv->buyer_trn }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-[13px] text-muted">{{ $inv->issue_date->format('d M Y') }}</td>
                    <td class="px-5 py-4 text-end">
                        <p class="text-[14px] font-semibold text-[#00d9b5] font-mono">{{ $inv->currency }} {{ number_format((float) $inv->total_inclusive, 2) }}</p>
                        <p class="text-[10px] text-muted">{{ __('tax_invoices.vat_short') }}: {{ number_format((float) $inv->total_tax, 2) }}</p>
                    </td>
                    <td class="px-5 py-4 text-center">
                        @if($inv->isVoided())
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 text-[#ff4d7f] text-[11px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#ff4d7f]"></span>
                                {{ __('tax_invoices.status_voided') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 text-[#00d9b5] text-[11px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                                {{ __('tax_invoices.status_issued') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-end">
                        <div class="inline-flex items-center gap-1">
                            <a href="{{ route('admin.tax-invoices.show', $inv->id) }}" class="px-2 py-1 rounded-[6px] text-[11px] font-medium text-accent hover:bg-accent/10">{{ __('common.view') }}</a>
                            <a href="{{ route('admin.tax-invoices.download', $inv->id) }}" class="px-2 py-1 rounded-[6px] text-[11px] font-medium text-primary hover:bg-page">{{ __('common.download') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <p class="text-[14px] text-muted">{{ __('tax_invoices.empty_state') }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $invoices->links() }}
</div>

@endsection
