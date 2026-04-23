<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma Invoice {{ $invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .header { border-bottom: 3px solid #4f7cff; padding-bottom: 15px; margin-bottom: 25px; }
        .header table { width: 100%; }
        .brand { font-size: 26px; font-weight: bold; color: #4f7cff; letter-spacing: 1px; }
        .doc-title { font-size: 22px; font-weight: bold; color: #1f2937; text-align: right; margin-bottom: 5px; }
        .doc-meta { text-align: right; color: #6b7280; font-size: 11px; line-height: 1.6; }
        .doc-meta strong { color: #1f2937; }

        .parties { width: 100%; margin-bottom: 20px; }
        .parties td { vertical-align: top; padding: 10px; width: 50%; }
        .party-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; font-weight: bold; margin-bottom: 6px; }
        .party-name { font-size: 14px; font-weight: bold; color: #1f2937; margin-bottom: 4px; }
        .party-detail { font-size: 11px; color: #4b5563; line-height: 1.5; }

        .section { margin-bottom: 18px; }
        .section-title { font-weight: bold; font-size: 13px; color: #4f7cff; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

        table.items { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.items th, table.items td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: left; }
        table.items th { background-color: #4f7cff; color: white; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.items td.r { text-align: right; }
        table.items td.c { text-align: center; }
        table.items tr:nth-child(even) td { background-color: #f9fafb; }

        .totals { width: 50%; float: right; margin-top: 10px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
        .totals td.label { color: #6b7280; }
        .totals td.value { text-align: right; font-weight: 600; }
        .totals tr.grand td { background-color: #4f7cff; color: white; font-size: 15px; font-weight: bold; border: none; padding: 10px; }

        .terms { clear: both; margin-top: 30px; padding: 12px; background-color: #f9fafb; border-left: 4px solid #4f7cff; }
        .terms-title { font-weight: bold; font-size: 12px; color: #1f2937; margin-bottom: 6px; }
        .terms-text { font-size: 11px; color: #4b5563; line-height: 1.6; }

        .schedule { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .schedule th, .schedule td { border: 1px solid #e5e7eb; padding: 8px; font-size: 11px; }
        .schedule th { background-color: #f3f4f6; text-transform: uppercase; letter-spacing: 0.5px; color: #4f7cff; font-size: 10px; }

        .stamp { margin-top: 30px; text-align: center; border: 2px dashed #9ca3af; padding: 15px; color: #6b7280; font-size: 11px; }
        .stamp strong { color: #4f7cff; text-transform: uppercase; letter-spacing: 1px; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="brand">TRILINK</div>
                <div style="font-size:11px; color:#6b7280; margin-top:4px;">B2B Procurement Platform · UAE</div>
            </td>
            <td style="width:50%;">
                <div class="doc-title">PROFORMA INVOICE</div>
                <div class="doc-meta">
                    <strong>No:</strong> {{ $invoice_number }}<br>
                    <strong>Date:</strong> {{ $issue_date->format('F j, Y') }}<br>
                    <strong>Bid Ref:</strong> BID-{{ $bid->updated_at?->format('Y') ?? date('Y') }}-{{ str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT) }}<br>
                    <strong>RFQ Ref:</strong> {{ $bid->rfq?->rfq_number ?? '—' }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="parties">
    <tr>
        <td style="background-color:#f9fafb; border-radius:6px;">
            <div class="party-label">Supplier (Bill From)</div>
            <div class="party-name">{{ $supplier?->name ?? '—' }}</div>
            <div class="party-detail">
                @if($supplier?->registration_number) Reg: {{ $supplier->registration_number }}<br>@endif
                @if($supplier?->tax_number) TRN: {{ $supplier->tax_number }}<br>@endif
                @if($supplier?->address){{ $supplier->address }}<br>@endif
                @if($supplier?->city){{ $supplier->city }}@if($supplier?->country), {{ $supplier->country }}@endif<br>@endif
                @if($supplier?->email){{ $supplier->email }}<br>@endif
                @if($supplier?->phone){{ $supplier->phone }}@endif
            </div>
        </td>
        <td style="background-color:#f9fafb; border-radius:6px;">
            <div class="party-label">Buyer (Bill To)</div>
            <div class="party-name">{{ $buyer?->name ?? '—' }}</div>
            <div class="party-detail">
                @if($buyer?->registration_number) Reg: {{ $buyer->registration_number }}<br>@endif
                @if($buyer?->tax_number) TRN: {{ $buyer->tax_number }}<br>@endif
                @if($buyer?->address){{ $buyer->address }}<br>@endif
                @if($buyer?->city){{ $buyer->city }}@if($buyer?->country), {{ $buyer->country }}@endif<br>@endif
                @if($buyer?->email){{ $buyer->email }}<br>@endif
                @if($buyer?->phone){{ $buyer->phone }}@endif
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Project Details</div>
    <table style="width:100%;">
        <tr>
            <td style="width:50%; padding:4px 0;"><span style="color:#6b7280;">Project:</span> <strong>{{ $bid->rfq?->title ?? '—' }}</strong></td>
            <td style="width:50%; padding:4px 0;"><span style="color:#6b7280;">Category:</span> <strong>{{ $bid->rfq?->category?->name ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><span style="color:#6b7280;">Delivery:</span> <strong>{{ (int) ($bid->delivery_time_days ?? 0) }} days</strong></td>
            <td style="padding:4px 0;"><span style="color:#6b7280;">Incoterms:</span> <strong>{{ $bid->incoterm ?? '—' }}</strong></td>
        </tr>
        @if($bid->hs_code || $bid->country_of_origin)
        <tr>
            @if($bid->hs_code)<td style="padding:4px 0;"><span style="color:#6b7280;">HS Code:</span> <strong>{{ $bid->hs_code }}</strong></td>@endif
            @if($bid->country_of_origin)<td style="padding:4px 0;"><span style="color:#6b7280;">Country of Origin:</span> <strong>{{ $bid->country_of_origin }}</strong></td>@endif
        </tr>
        @endif
    </table>
</div>

<div class="section">
    <div class="section-title">Line Items</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Description</th>
                <th class="c" style="width:70px;">Qty</th>
                <th class="c" style="width:70px;">Unit</th>
                <th class="r" style="width:110px;">Unit Price</th>
                <th class="r" style="width:120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $items = is_array($bid->items) ? $bid->items : []; @endphp
            @forelse($items as $i => $it)
                @php
                    $qty = (int) ($it['qty'] ?? 0);
                    $unitPrice = (float) ($it['unit_price'] ?? 0);
                    $lineTotal = $qty * $unitPrice;
                @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $it['name'] ?? 'Item' }}</td>
                    <td class="c">{{ $qty ?: '—' }}</td>
                    <td class="c">{{ $it['unit'] ?? '—' }}</td>
                    <td class="r">{{ $unitPrice ? number_format($unitPrice, 2) : '—' }}</td>
                    <td class="r">{{ $lineTotal ? number_format($lineTotal, 2) : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c">1</td>
                    <td>{{ $bid->rfq?->title ?? 'Bid as proposed' }}</td>
                    <td class="c">1</td>
                    <td class="c">lot</td>
                    <td class="r">{{ number_format($subtotal, 2) }}</td>
                    <td class="r">{{ number_format($subtotal, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="totals">
    <table>
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ $currency }} {{ number_format($subtotal, 2) }}</td>
        </tr>
        @if($tax_rate > 0)
        <tr>
            <td class="label">VAT ({{ number_format($tax_rate, 2) }}%)</td>
            <td class="value">{{ $currency }} {{ number_format($tax_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td>Total Due</td>
            <td style="text-align:right;">{{ $currency }} {{ number_format($total, 2) }}</td>
        </tr>
    </table>
</div>

<div style="clear:both;"></div>

@if(!empty($schedule))
<div class="section" style="margin-top:25px;">
    <div class="section-title">Payment Schedule</div>
    <table class="schedule">
        <thead>
            <tr>
                <th>Milestone</th>
                <th class="r" style="width:80px;">%</th>
                <th class="r" style="width:160px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule as $row)
                <tr>
                    <td>{{ $row['milestone'] ?? '—' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) ($row['percentage'] ?? 0), 2), '0'), '.') }}%</td>
                    <td class="r">{{ $row['amount'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="terms">
    <div class="terms-title">Terms & Conditions</div>
    <div class="terms-text">
        <strong>Payment Terms:</strong> {{ $payment_terms_en ?? 'As agreed between parties' }}<br>
        <strong>Validity:</strong> This proforma invoice is valid until {{ $bid->validity_date?->format('F j, Y') ?? now()->addDays(30)->format('F j, Y') }}.<br>
        <strong>Currency:</strong> All amounts are in {{ $currency }}.<br>
        <strong>Note:</strong> This is a proforma invoice issued upon acceptance of the bid. It is not a tax invoice and does not constitute a demand for payment. The final VAT-compliant tax invoice will be issued by the supplier once the contract is signed and payment is processed.
    </div>
</div>

<div class="stamp">
    <strong>Proforma Invoice — Not a Tax Document</strong><br>
    <span style="font-size:10px;">Generated from accepted bid on {{ $issue_date->format('F j, Y · g:i A') }}</span>
</div>

<div class="footer">
    Trilink Procurement Platform · Generated automatically from bid acceptance<br>
    This document is an electronic proforma and is valid without a signature
</div>

</body>
</html>
