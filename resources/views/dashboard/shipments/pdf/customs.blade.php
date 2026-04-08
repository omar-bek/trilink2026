{{--
    Phase 6 — Customs document PDF template. Renders both commercial
    invoices and packing lists from a single template; the doc.type
    field switches between the line-item table (invoice) and the
    package table (packing list).

    Inputs:
      $doc — payload built by CustomsDocumentService
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $doc['document_number'] }}</title>
    <style>
        @page { margin: 30px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1d29; }
        h1 { font-size: 18px; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        h2 { font-size: 12px; margin: 0 0 4px; text-transform: uppercase; color: #4f7cff; }
        .header { border-bottom: 2px solid #4f7cff; padding-bottom: 12px; margin-bottom: 16px; }
        .meta { color: #6b7280; font-size: 10px; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; padding: 8px 0; }
        .block { padding: 10px; background: #f5f7fb; border-radius: 6px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.lines th, table.lines td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        table.lines th { background: #4f7cff; color: white; font-size: 10px; text-transform: uppercase; }
        table.lines tr.total td { font-weight: bold; background: #f5f7fb; }
        .right { text-align: right; }
        .footer { margin-top: 25px; font-size: 9px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $doc['document_type'] === 'commercial_invoice' ? 'Commercial Invoice' : 'Packing List' }}</h1>
    <div class="meta">
        Document #: {{ $doc['document_number'] }} &middot;
        Issued: {{ $doc['issue_date'] }} &middot;
        Tracking: {{ $doc['shipment']['tracking_number'] ?? '—' }}
    </div>
</div>

<table class="grid">
    <tr>
        <td style="width: 50%;">
            <div class="block">
                <h2>Shipper</h2>
                <strong>{{ $doc['shipper']['name'] ?? '—' }}</strong><br>
                {{ $doc['shipper']['address'] ?? '' }}<br>
                {{ $doc['shipper']['country'] ?? '' }}<br>
                @if(!empty($doc['shipper']['tax_no']))Tax #: {{ $doc['shipper']['tax_no'] }}@endif
            </div>
        </td>
        <td style="width: 50%; padding-left: 12px;">
            <div class="block">
                <h2>Consignee</h2>
                <strong>{{ $doc['consignee']['name'] ?? '—' }}</strong><br>
                {{ $doc['consignee']['address'] ?? '' }}<br>
                {{ $doc['consignee']['country'] ?? '' }}<br>
                @if(!empty($doc['consignee']['tax_no']))Tax #: {{ $doc['consignee']['tax_no'] }}@endif
            </div>
        </td>
    </tr>
</table>

@if($doc['document_type'] === 'commercial_invoice')
<table class="lines">
    <thead>
        <tr>
            <th>Description</th>
            <th>HS Code</th>
            <th class="right">Qty</th>
            <th class="right">Unit Price</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($doc['lines'] as $line)
        <tr>
            <td>{{ $line['description'] }}</td>
            <td>{{ $line['hs_code'] ?? '—' }}</td>
            <td class="right">{{ $line['quantity'] }}</td>
            <td class="right">{{ $line['currency'] }} {{ number_format($line['unit_price'], 2) }}</td>
            <td class="right">{{ $line['currency'] }} {{ number_format($line['line_total'], 2) }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td colspan="4" class="right">Subtotal</td>
            <td class="right">{{ $doc['totals']['currency'] }} {{ number_format($doc['totals']['subtotal'], 2) }}</td>
        </tr>
        <tr class="total">
            <td colspan="4" class="right">Tax</td>
            <td class="right">{{ $doc['totals']['currency'] }} {{ number_format($doc['totals']['tax'], 2) }}</td>
        </tr>
        <tr class="total">
            <td colspan="4" class="right">Total</td>
            <td class="right">{{ $doc['totals']['currency'] }} {{ number_format($doc['totals']['total'], 2) }}</td>
        </tr>
    </tbody>
</table>
@else
<table class="lines">
    <thead>
        <tr>
            <th>Pkg #</th>
            <th>Marks</th>
            <th>Description</th>
            <th class="right">Qty</th>
            <th class="right">Net (kg)</th>
            <th class="right">Gross (kg)</th>
            <th>Dimensions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($doc['packages'] as $pkg)
        <tr>
            <td>{{ $pkg['package_no'] }}</td>
            <td>{{ $pkg['marks'] }}</td>
            <td>{{ $pkg['description'] }}</td>
            <td class="right">{{ $pkg['quantity'] }}</td>
            <td class="right">{{ number_format($pkg['net_kg'], 1) }}</td>
            <td class="right">{{ number_format($pkg['gross_kg'], 1) }}</td>
            <td>{{ $pkg['dimensions'] }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td colspan="3" class="right">Totals</td>
            <td class="right">—</td>
            <td class="right">{{ number_format($doc['totals']['net_kg'], 1) }}</td>
            <td class="right">{{ number_format($doc['totals']['gross_kg'], 1) }}</td>
            <td>{{ $doc['totals']['package_count'] }} pkg</td>
        </tr>
    </tbody>
</table>
@endif

@if(!empty($doc['notes']))
<p style="margin-top: 15px; font-size: 10px; color: #6b7280;">{{ $doc['notes'] }}</p>
@endif

<div class="footer">
    Generated by TriLink &middot; This document is an automated export of platform records.
</div>

</body>
</html>
