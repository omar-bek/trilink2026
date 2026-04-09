@php
    /**
     * UAE-grade Tax Credit Note PDF.
     *
     * Drafted to satisfy Cabinet Decision 52/2017 Article 60. The credit
     * note must reference the original invoice and carry an explicit
     * reason for the reversal.
     */

    $original = $creditNote->originalInvoice;
    $currency = $creditNote->currency;

    $fmt = function ($v) {
        return number_format((float) $v, 2, '.', ',');
    };

    // Reason translation key
    $reasonKey = 'tax_invoices.credit_reason_' . $creditNote->reason;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $creditNote->credit_note_number }}</title>
    <style>
        @page { margin: 35px 30px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.4; }
        .header { border-bottom: 3px solid #b91c1c; padding-bottom: 14px; margin-bottom: 14px; }
        .header-bar { width: 100%; display: table; }
        .header-bar > div { display: table-cell; vertical-align: middle; }
        .header-left { width: 50%; }
        .header-right { width: 50%; text-align: left; direction: ltr; }
        .doc-label-ar { font-size: 22px; font-weight: bold; color: #b91c1c; text-align: right; }
        .doc-label-en { font-size: 18px; font-weight: bold; color: #b91c1c; text-align: left; }
        .doc-num { font-size: 11px; color: #444; margin-top: 4px; }
        .doc-num strong { font-family: monospace; color: #000; }
        .ref-block {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            padding: 10px 12px;
            margin: 10px 0 14px;
            font-size: 10px;
        }
        .ref-block strong { color: #b91c1c; }
        .parties { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .parties td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #d4d4d8; background: #f9fafb; }
        .party-label { font-size: 9px; text-transform: uppercase; color: #525252; letter-spacing: 0.5px; margin-bottom: 4px; }
        .party-name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .party-line { font-size: 9px; color: #404040; margin: 1px 0; }
        .party-trn { font-family: monospace; font-weight: bold; color: #b91c1c; }
        table.lines { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9px; }
        table.lines thead th {
            background: #b91c1c; color: white; padding: 6px 5px;
            text-align: center; font-weight: bold; font-size: 9px;
            border: 1px solid #b91c1c;
        }
        table.lines tbody td { border: 1px solid #d4d4d8; padding: 6px 5px; vertical-align: top; }
        table.lines tbody tr:nth-child(even) td { background: #fafafa; }
        .text-end { text-align: right; }
        .text-start { text-align: left; }
        .text-center { text-align: center; }
        .totals { width: 100%; margin-top: 8px; }
        .totals td { padding: 4px 12px; font-size: 10px; }
        .totals .label { text-align: end; color: #525252; }
        .totals .value { text-align: end; font-family: monospace; width: 30%; }
        .totals .grand .label {
            font-weight: bold; color: #1a1a1a; font-size: 12px;
            padding-top: 8px; border-top: 2px solid #b91c1c;
        }
        .totals .grand .value {
            font-weight: bold; color: #b91c1c; font-size: 14px;
            padding-top: 8px; border-top: 2px solid #b91c1c;
        }
        .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #d4d4d8; font-size: 8px; color: #6b6b6b; }
        .footer p { margin: 3px 0; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-bar">
            <div class="header-left">
                <div class="doc-label-ar">إشعار دائن ضريبي</div>
                <div class="doc-num">رقم: <strong>{{ $creditNote->credit_note_number }}</strong></div>
            </div>
            <div class="header-right">
                <div class="doc-label-en">TAX CREDIT NOTE</div>
                <div class="doc-num">No: <strong>{{ $creditNote->credit_note_number }}</strong></div>
            </div>
        </div>
    </div>

    {{-- Original invoice reference --}}
    <div class="ref-block">
        <strong>إشعار دائن مقابل الفاتورة الضريبية رقم / Credit note against tax invoice:</strong>
        <span style="font-family: monospace;">{{ $original->invoice_number ?? '—' }}</span>
        &nbsp;|&nbsp;
        <strong>السبب / Reason:</strong> {{ __($reasonKey) }}
        &nbsp;|&nbsp;
        <strong>تاريخ الإصدار / Issued:</strong> {{ $creditNote->issue_date->format('d M Y') }}
    </div>

    {{-- Parties (snapshot from original invoice) --}}
    @if($original)
    <table class="parties">
        <tr>
            <td>
                <div class="party-label">المورّد / SUPPLIER</div>
                <div class="party-name">{{ $original->supplier_name }}</div>
                @if($original->supplier_trn)
                    <div class="party-line">TRN: <span class="party-trn">{{ $original->supplier_trn }}</span></div>
                @endif
                @if($original->supplier_address)
                    <div class="party-line">{{ $original->supplier_address }}</div>
                @endif
            </td>
            <td>
                <div class="party-label">المشتري / BUYER</div>
                <div class="party-name">{{ $original->buyer_name }}</div>
                @if($original->buyer_trn)
                    <div class="party-line">TRN: <span class="party-trn">{{ $original->buyer_trn }}</span></div>
                @endif
                @if($original->buyer_address)
                    <div class="party-line">{{ $original->buyer_address }}</div>
                @endif
            </td>
        </tr>
    </table>
    @endif

    {{-- Line items being credited --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 40%;" class="text-start">الوصف / Description</th>
                <th style="width: 8%;">الكمية / Qty</th>
                <th style="width: 14%;" class="text-end">سعر الوحدة / Unit Price</th>
                <th style="width: 8%;" class="text-end">VAT %</th>
                <th style="width: 12%;" class="text-end">قيمة الضريبة / VAT</th>
                <th style="width: 14%;" class="text-end">الإجمالي / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditNote->line_items as $i => $line)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="text-start">{{ $line['description'] ?? '—' }}</td>
                    <td class="text-center">{{ $fmt($line['quantity'] ?? 0) }}</td>
                    <td class="text-end">- {{ $currency }} {{ $fmt($line['unit_price'] ?? 0) }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) ($line['tax_rate'] ?? 0), 2), '0'), '.') }}%</td>
                    <td class="text-end">- {{ $currency }} {{ $fmt($line['tax_amount'] ?? 0) }}</td>
                    <td class="text-end">- {{ $currency }} {{ $fmt($line['line_total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">المجموع الفرعي قبل الضريبة / Subtotal (excl. VAT)</td>
            <td class="value">- {{ $currency }} {{ $fmt($creditNote->subtotal_excl_tax) }}</td>
        </tr>
        <tr>
            <td class="label">إجمالي الضريبة / Total VAT</td>
            <td class="value">- {{ $currency }} {{ $fmt($creditNote->total_tax) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">القيمة الإجمالية للإشعار الدائن / Total Credit Note Amount</td>
            <td class="value">- {{ $currency }} {{ $fmt($creditNote->total_inclusive) }}</td>
        </tr>
    </table>

    @if($creditNote->notes)
    <div style="margin-top: 14px; padding: 10px; background: #fafafa; border: 1px solid #e5e5e5; font-size: 9px;">
        <strong>ملاحظات / Notes:</strong> {{ $creditNote->notes }}
    </div>
    @endif

    <div class="footer">
        <p>
            <strong>هذا إشعار دائن ضريبي رسمي صادر وفقاً للمادة 60 من قرار مجلس الوزراء رقم 52 لسنة 2017.</strong><br>
            This is an official tax credit note issued pursuant to Article 60 of UAE Cabinet Decision No. 52 of 2017.
        </p>
        <p>
            النسخة العربية تسود في حال أي خلاف في التفسير أمام المحاكم الإماراتية.<br>
            The Arabic version prevails in case of any interpretation discrepancy before UAE courts.
        </p>
    </div>

</body>
</html>
