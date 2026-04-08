<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RFQ {{ $rfq->rfq_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { text-align: center; color: #4f7cff; margin-bottom: 5px; }
        .subtitle { text-align: center; color: #888; margin-bottom: 25px; }
        .header { border-bottom: 2px solid #4f7cff; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 18px; }
        .section-title { font-weight: bold; font-size: 14px; color: #4f7cff; margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
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

<h1>REQUEST FOR QUOTATION</h1>
<p class="subtitle">{{ $rfq->rfq_number }} &middot; {{ $rfq->created_at?->format('F j, Y') }}</p>

<div class="section">
    <div class="section-title">Project Title</div>
    <p class="value">{{ $rfq->title }}</p>
</div>

@if($rfq->description)
<div class="section">
    <div class="section-title">Description</div>
    <p>{{ $rfq->description }}</p>
</div>
@endif

<div class="section">
    <div class="section-title">Summary</div>
    <div class="grid">
        <div class="col">
            <p class="label">Buyer</p>
            <p class="value">{{ $rfq->is_anonymous ? 'Anonymous Buyer' : ($rfq->company?->name ?? '—') }}</p>
        </div>
        <div class="col">
            <p class="label">Category</p>
            <p class="value">{{ $rfq->category?->name ?? '—' }}</p>
        </div>
    </div>
    <div class="grid" style="margin-top:8px;">
        <div class="col">
            <p class="label">Budget</p>
            <p class="amount">{{ ($rfq->currency ?? 'AED') . ' ' . number_format((float) $rfq->budget, 2) }}</p>
        </div>
        <div class="col">
            <p class="label">Deadline</p>
            <p class="value">{{ optional($rfq->deadline)->format('F j, Y') ?? '—' }}</p>
        </div>
    </div>
</div>

@if(!empty($rfq->items))
<div class="section">
    <div class="section-title">Line Items</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Specification</th>
                <th class="r">Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rfq->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['name'] ?? '—' }}</td>
                <td>{{ $item['spec'] ?? $item['description'] ?? '—' }}</td>
                <td class="r">{{ number_format((float) ($item['qty'] ?? $item['quantity'] ?? 0)) }}</td>
                <td>{{ $item['unit'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <div class="section-title">Delivery</div>
    @php
        $loc = $rfq->delivery_location;
        if (is_array($loc)) {
            $loc = trim(implode(', ', array_filter([
                $loc['address'] ?? null,
                $loc['city'] ?? null,
                $loc['country'] ?? null,
            ]))) ?: '—';
        }
    @endphp
    <p class="value">{{ $loc ?: '—' }}</p>
</div>

<div class="footer">
    Generated on {{ now()->format('F j, Y \a\t g:i A') }}.<br>
    TriLink Trading Platform · Privacy-protected RFQ
</div>

</body>
</html>
