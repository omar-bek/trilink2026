@extends('layouts.dashboard', ['active' => 'admin-tax-invoices'])
@section('title', $invoice->invoice_number)

@section('content')

<a href="{{ route('admin.tax-invoices.index') }}" class="inline-flex items-center gap-2 text-[14px] text-muted hover:text-primary mb-4 transition-colors">
    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    {{ __('tax_invoices.back_to_list') }}
</a>

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <div class="flex items-center gap-3 flex-wrap mb-1">
            <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em] font-mono">{{ $invoice->invoice_number }}</h1>
            @if($invoice->isVoided())
                <span class="inline-flex items-center gap-1.5 h-[28px] px-3 rounded-full bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 text-[#ff4d7f] text-[12px] font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff4d7f]"></span>
                    {{ __('tax_invoices.status_voided') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 h-[28px] px-3 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 text-[#00d9b5] text-[12px] font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                    {{ __('tax_invoices.status_issued') }}
                </span>
            @endif
        </div>
        <p class="text-[14px] text-muted">{{ __('tax_invoices.issued_on', ['date' => $invoice->issued_at?->format('d M Y, H:i')]) }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.tax-invoices.download', $invoice->id) }}"
           class="inline-flex items-center gap-2 px-5 h-12 rounded-[12px] text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            {{ __('tax_invoices.download_pdf') }}
        </a>
        @if(!$invoice->isVoided())
            <form method="POST" action="{{ route('admin.tax-invoices.void', $invoice->id) }}" class="inline"
                  onsubmit="const reason = prompt('{{ __('tax_invoices.void_prompt') }}'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason">
                <button type="submit" class="inline-flex items-center gap-2 px-5 h-12 rounded-[12px] text-[14px] font-medium text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/15 transition-colors">
                    {{ __('tax_invoices.void_invoice') }}
                </button>
            </form>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="bg-[#00d9b5]/10 border border-[#00d9b5]/30 rounded-[12px] p-4 mb-6 text-[14px] text-[#00d9b5]">
        {{ session('status') }}
    </div>
@endif

{{-- Voided banner --}}
@if($invoice->isVoided())
    <div class="bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 rounded-[12px] p-4 mb-6">
        <p class="text-[14px] font-semibold text-[#ff4d7f]">{{ __('tax_invoices.voided_banner', ['date' => $invoice->voided_at?->format('d M Y, H:i')]) }}</p>
        @if($invoice->void_reason)
            <p class="text-[13px] text-muted mt-1">{{ __('tax_invoices.void_reason_label') }}: {{ $invoice->void_reason }}</p>
        @endif
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        {{-- Parties --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('tax_invoices.parties') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <p class="text-[11px] uppercase tracking-wider font-semibold text-muted mb-2">{{ __('tax_invoices.supplier') }}</p>
                    <p class="text-[15px] font-bold text-primary">{{ $invoice->supplier_name }}</p>
                    @if($invoice->supplier_trn)
                        <p class="text-[12px] text-muted mt-1">TRN: <span class="text-primary font-mono">{{ $invoice->supplier_trn }}</span></p>
                    @endif
                    @if($invoice->supplier_address)
                        <p class="text-[12px] text-muted mt-1">{{ $invoice->supplier_address }}</p>
                    @endif
                </div>
                <div class="bg-page border border-th-border rounded-[12px] p-4">
                    <p class="text-[11px] uppercase tracking-wider font-semibold text-muted mb-2">{{ __('tax_invoices.buyer') }}</p>
                    <p class="text-[15px] font-bold text-primary">{{ $invoice->buyer_name }}</p>
                    @if($invoice->buyer_trn)
                        <p class="text-[12px] text-muted mt-1">TRN: <span class="text-primary font-mono">{{ $invoice->buyer_trn }}</span></p>
                    @endif
                    @if($invoice->buyer_address)
                        <p class="text-[12px] text-muted mt-1">{{ $invoice->buyer_address }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('tax_invoices.line_items') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-surface-2/40 border-b border-th-border">
                        <tr>
                            <th class="text-start px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('bids.item') }}</th>
                            <th class="text-end px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('bids.qty') }}</th>
                            <th class="text-end px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('bids.unit_price') }}</th>
                            <th class="text-end px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">VAT %</th>
                            <th class="text-end px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('bids.vat') }}</th>
                            <th class="text-end px-3 py-2 text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->line_items as $line)
                            <tr class="border-b border-th-border">
                                <td class="px-3 py-3 text-[13px] text-primary">{{ $line['description'] ?? '—' }}</td>
                                <td class="px-3 py-3 text-end text-[13px] text-muted">{{ $line['quantity'] ?? 0 }} {{ $line['unit'] ?? '' }}</td>
                                <td class="px-3 py-3 text-end text-[13px] text-muted font-mono">{{ $invoice->currency }} {{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                                <td class="px-3 py-3 text-end text-[13px] text-muted">{{ rtrim(rtrim(number_format((float) ($line['tax_rate'] ?? 0), 2), '0'), '.') }}%</td>
                                <td class="px-3 py-3 text-end text-[13px] text-muted font-mono">{{ $invoice->currency }} {{ number_format((float) ($line['tax_amount'] ?? 0), 2) }}</td>
                                <td class="px-3 py-3 text-end text-[13px] font-semibold text-[#00d9b5] font-mono">{{ $invoice->currency }} {{ number_format((float) ($line['line_total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-4 border-t border-th-border space-y-2">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-muted">{{ __('bids.subtotal') }}</span>
                    <span class="text-primary font-mono">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal_excl_tax, 2) }}</span>
                </div>
                <div class="flex items-center justify-between text-[13px]">
                    <span class="text-muted">{{ __('bids.vat') }}</span>
                    <span class="text-primary font-mono">{{ $invoice->currency }} {{ number_format((float) $invoice->total_tax, 2) }}</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-th-border">
                    <span class="text-[14px] font-bold text-primary">{{ __('common.total') }}</span>
                    <span class="text-[20px] font-bold text-[#00d9b5] font-mono">{{ $invoice->currency }} {{ number_format((float) $invoice->total_inclusive, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Credit notes (if any) --}}
        @if($invoice->creditNotes->isNotEmpty())
            <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
                <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('tax_invoices.related_credit_notes') }}</h3>
                <div class="space-y-2">
                    @foreach($invoice->creditNotes as $cn)
                        <div class="flex items-center justify-between bg-page border border-th-border rounded-[10px] px-4 py-3">
                            <div>
                                <p class="text-[13px] font-mono font-semibold text-[#b91c1c]">{{ $cn->credit_note_number }}</p>
                                <p class="text-[11px] text-muted mt-0.5">{{ __('tax_invoices.credit_reason_' . $cn->reason) }}</p>
                            </div>
                            <p class="text-[13px] font-mono text-[#b91c1c]">- {{ $cn->currency }} {{ number_format((float) $cn->total_inclusive, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- Sidebar --}}
    <div class="space-y-4">
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-primary mb-4">{{ __('tax_invoices.metadata') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.col_issue_date') }}</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->issue_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.col_supply_date') }}</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->supply_date->format('d M Y') }}</dd>
                </div>
                @if($invoice->payment_id)
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.linked_payment') }}</dt>
                    <dd class="text-accent mt-0.5">#{{ $invoice->payment_id }}</dd>
                </div>
                @endif
                @if($invoice->contract_id)
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.linked_contract') }}</dt>
                    <dd class="text-accent mt-0.5">#{{ $invoice->contract_id }}</dd>
                </div>
                @endif
                @if($invoice->issuer)
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.issued_by') }}</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->issuer->full_name ?? $invoice->issuer->email }}</dd>
                </div>
                @endif
                @if($invoice->pdf_sha256)
                <div>
                    <dt class="text-muted text-[12px]">{{ __('tax_invoices.pdf_hash') }}</dt>
                    <dd class="text-primary mt-0.5 font-mono text-[10px] break-all">{{ $invoice->pdf_sha256 }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>

@endsection
