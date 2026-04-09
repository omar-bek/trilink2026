@extends('layouts.dashboard', ['active' => 'admin-e-invoice'])
@section('title', __('einvoice.admin_index_title'))

@section('content')

<div class="mb-6">
    <h1 class="text-[28px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('einvoice.admin_index_title') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('einvoice.admin_index_subtitle') }}</p>
</div>

{{-- Pipeline status banner --}}
<div class="mb-6 p-4 rounded-2xl border {{ $einvoiceEnabled ? 'bg-[#00d9b5]/5 border-[#00d9b5]/30' : 'bg-[#ffb020]/5 border-[#ffb020]/30' }}">
    <div class="flex items-start gap-3">
        @if($einvoiceEnabled)
            <svg class="w-5 h-5 text-[#00d9b5] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m6 2.25c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
        @else
            <svg class="w-5 h-5 text-[#ffb020] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        @endif
        <div class="flex-1 text-[13px]">
            <p class="font-semibold text-primary">
                {{ $einvoiceEnabled ? __('einvoice.banner_enabled') : __('einvoice.banner_disabled') }}
            </p>
            <p class="text-muted mt-1">
                {{ __('einvoice.banner_provider', ['provider' => strtoupper($currentProvider), 'env' => strtoupper($currentEnv)]) }}
            </p>
        </div>
    </div>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">
        {{ session('status') }}
    </div>
@endif
@if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[13px] text-[#ff4d7f]">
        {{ $errors->first() }}
    </div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-muted">{{ $stats['queued'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('einvoice.stat_queued') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-accent">{{ $stats['submitted'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('einvoice.stat_submitted') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#00d9b5]">{{ $stats['accepted'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('einvoice.stat_accepted') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['rejected'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('einvoice.stat_rejected') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['failed'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('einvoice.stat_failed') }}</p>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('einvoice.search_placeholder') }}"
           class="flex-1 bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60">
    <select name="status" class="bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60">
        <option value="">{{ __('common.all') }}</option>
        @foreach(['queued', 'submitted', 'accepted', 'rejected', 'failed'] as $s)
            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('einvoice.status_' . $s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-5 h-12 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
</form>

{{-- List --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full">
        <thead class="bg-page border-b border-th-border">
            <tr>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('einvoice.col_invoice') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('einvoice.col_provider') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('einvoice.col_clearance') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('einvoice.col_total') }}</th>
                <th class="text-center px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($submissions as $sub)
                <tr class="border-b border-th-border hover:bg-page/40 transition-colors">
                    <td class="px-5 py-4">
                        @if($sub->taxInvoice)
                            <a href="{{ route('admin.tax-invoices.show', $sub->taxInvoice->id) }}" class="text-[13px] font-mono font-semibold text-accent hover:underline">{{ $sub->taxInvoice->invoice_number }}</a>
                            <p class="text-[10px] text-muted truncate max-w-[200px]">{{ $sub->taxInvoice->supplier_name }} → {{ $sub->taxInvoice->buyer_name }}</p>
                        @else
                            <span class="text-[12px] text-muted">{{ __('einvoice.invoice_deleted') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[12px] uppercase font-semibold text-primary">{{ $sub->asp_provider }}</p>
                        <p class="text-[10px] text-muted">{{ $sub->asp_environment }}</p>
                    </td>
                    <td class="px-5 py-4">
                        @if($sub->fta_clearance_id)
                            <p class="text-[11px] font-mono text-[#00d9b5]">{{ $sub->fta_clearance_id }}</p>
                        @elseif($sub->asp_submission_id)
                            <p class="text-[11px] font-mono text-muted">{{ $sub->asp_submission_id }}</p>
                        @else
                            <p class="text-[11px] text-muted">—</p>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-end">
                        @if($sub->taxInvoice)
                            <p class="text-[13px] font-semibold text-primary font-mono">{{ $sub->taxInvoice->currency }} {{ number_format((float) $sub->taxInvoice->total_inclusive, 2) }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-center">
                        @php $colors = ['queued' => '#525252', 'submitted' => '#4f7cff', 'accepted' => '#00d9b5', 'rejected' => '#ff4d7f', 'failed' => '#ffb020']; $c = $colors[$sub->status] ?? '#525252'; @endphp
                        <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full text-[11px] font-semibold" style="background: {{ $c }}1A; border: 1px solid {{ $c }}33; color: {{ $c }};">
                            {{ __('einvoice.status_' . $sub->status) }}
                        </span>
                        @if($sub->retries > 0)
                            <p class="text-[10px] text-muted mt-1">{{ __('einvoice.retries_count', ['n' => $sub->retries]) }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-end">
                        @if(in_array($sub->status, ['failed', 'rejected'], true))
                            <form method="POST" action="{{ route('admin.e-invoice.retry', $sub->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-accent hover:bg-accent/10">{{ __('einvoice.retry') }}</button>
                            </form>
                        @else
                            <span class="text-[10px] text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @if($sub->error_message)
                    <tr class="border-b border-th-border">
                        <td colspan="6" class="px-5 py-2 bg-[#ff4d7f]/5">
                            <p class="text-[11px] text-[#ff4d7f]"><span class="font-semibold">{{ __('einvoice.error') }}:</span> {{ $sub->error_message }}</p>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center"><p class="text-[14px] text-muted">{{ __('einvoice.empty_state') }}</p></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $submissions->links() }}
</div>

@endsection
