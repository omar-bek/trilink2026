<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php $bidNumber = 'BID-' . ($bid->created_at?->format('Y') ?? date('Y')) . '-' . str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT); @endphp
    <title>Bid {{ $bidNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { text-align: center; color: #4f7cff; margin-bottom: 5px; }
        .subtitle { text-align: center; color: #888; margin-bottom: 25px; }
        .header { border-bottom: 2px solid #4f7cff; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 18px; }
        .section-title { font-weight: bold; font-size: 14px; color: #4f7cff; margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .grid { width: 100%; }
        .grid .col { display: inline-block; width: 48%; vertical-align: top; margin-right: 1%; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .value { font-weight: 600; font-size: 13px; margin-top: 2px; }
        .amount { color: #00d9b5; font-weight: 700; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background-color: #f9fafb; color: #4f7cff; font-size: 11px; text-transform: uppercase; }
        td.r { text-align: right; }
        .footer { margin-top: 40px; text-align: center; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>

<h1>BID PROPOSAL</h1>
<p class="subtitle">{{ $bidNumber }}
    &middot; {{ $bid->created_at?->format('F j, Y') }}</p>

<div class="section">
    <div class="section-title">RFQ Reference</div>
    <div class="grid">
        <div class="col">
            <p class="label">RFQ Number</p>
            <p class="value">#{{ $bid->rfq?->rfq_number ?? '—' }}</p>
        </div>
        <div class="col">
            <p class="label">Category</p>
            <p class="value">{{ $bid->rfq?->category?->name ?? '—' }}</p>
        </div>
    </div>
    <p class="label" style="margin-top:8px;">Project</p>
    <p class="value">{{ $bid->rfq?->title ?? '—' }}</p>
</div>

<div class="section">
    <div class="section-title">Bid Amount</div>
    <p class="amount">{{ $bid->currency ?? 'AED' }} {{ number_format((float) $bid->price, 2) }}</p>
</div>

<div class="section">
    <div class="section-title">Terms</div>
    <div class="grid">
        <div class="col">
            <p class="label">Delivery Time</p>
            <p class="value">{{ (int) ($bid->delivery_time_days ?? 0) }} days from contract signing</p>
        </div>
        <div class="col">
            <p class="label">Valid Until</p>
            <p class="value">{{ optional($bid->validity_date)->format('F j, Y') ?? '—' }}</p>
        </div>
    </div>
    <div style="margin-top:8px;">
        <p class="label">Payment Terms</p>
        <p class="value">{{ $bid->payment_terms ?? '—' }}</p>
    </div>
</div>

@if(!empty($schedule))
<div class="section">
    <div class="section-title">Payment Schedule</div>
    <table>
        <thead>
            <tr>
                <th>Milestone</th>
                <th class="r">%</th>
                <th class="r">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule as $row)
            <tr>
                <td>{{ $row['milestone'] }}</td>
                <td class="r">{{ $row['percentage'] }}%</td>
                <td class="r">{{ $row['amount'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if(!empty($bid->items))
<div class="section">
    <div class="section-title">Line Items</div>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="r">Qty</th>
                <th>Unit</th>
                <th class="r">Unit Price</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bid->items as $item)
            @php
                $qty = (float) ($item['qty'] ?? 0);
                $unit_price = (float) ($item['unit_price'] ?? 0);
            @endphp
            <tr>
                <td>{{ $item['name'] ?? '' }}</td>
                <td class="r">{{ number_format($qty) }}</td>
                <td>{{ $item['unit'] ?? '' }}</td>
                <td class="r">{{ ($bid->currency ?? 'AED') . ' ' . number_format($unit_price, 2) }}</td>
                <td class="r">{{ ($bid->currency ?? 'AED') . ' ' . number_format($unit_price * max($qty, 1), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($bid->notes)
<div class="section">
    <div class="section-title">Additional Notes</div>
    <p>{{ $bid->notes }}</p>
</div>
@endif

<div class="section">
    <div class="section-title">Submitted By</div>
    <p class="value">{{ $bid->company?->name ?? '—' }}</p>
</div>

<div class="footer">
    This document is a proposal summary generated on {{ now()->format('F j, Y \a\t g:i A') }}.<br>
    TriLink Trading Platform
</div>

</body>
</html>
