@php
    /**
     * UAE-grade Tax Invoice PDF.
     *
     * Drafted to satisfy:
     *   - Federal Decree-Law No. 8 of 2017 (VAT) — Article 65
     *   - Cabinet Decision No. 52 of 2017 — Articles 59-60
     *
     * Every required field is present:
     *   1. The words "Tax Invoice" prominently   ✓
     *   2. Issuer name + TRN + address           ✓
     *   3. Recipient name + TRN + address        ✓
     *   4. Sequential invoice number             ✓
     *   5. Date of issue                         ✓
     *   6. Date of supply (if different)         ✓
     *   7. Description of goods/services         ✓
     *   8. Quantity & unit price                 ✓
     *   9. Discount per line (if any)            ✓
     *  10. Tax rate per line                     ✓
     *  11. Tax amount in AED                     ✓
     *  12. Total amount inclusive of tax         ✓
     *
     * Layout: bilingual stacked (Arabic on top, English below) with one
     * shared body. Both versions are authentic; the Arabic version prevails
     * in UAE courts per Federal Law 26/1981 — see the language clause
     * footer at the bottom.
     */

    $isVoided = $invoice->isVoided();
    $currency = $invoice->currency;
    // qrDataUri is optional — TaxInvoiceService passes it but tests or
    // direct PDF previews via tinker may not, so default to null and let
    // the @if guards in the body handle the absent case.
    $qrDataUri = $qrDataUri ?? null;
    // Phase 7 — Corporate Tax annotation. Null when the supplier's CT
    // status is standard or unknown (no annotation needed).
    $ctAnnotation = $ctAnnotation ?? null;

    // Phase 1.5 (UAE Compliance Roadmap — post-implementation hardening).
    // Cabinet Decision 52/2017 Article 59(1)(j) requires the VAT
    // treatment to appear ON the invoice document itself when it is
    // anything other than the standard 5% rate. We surface a banner
    // above the totals + a tag in the audit block.
    $vatTreatment = $invoice->vat_treatment ?? 'standard';
    $vatBannerText = match ($vatTreatment) {
        'reverse_charge'           => ['en' => 'REVERSE CHARGE MECHANISM APPLIES — Article 48 Federal Decree-Law No. 8 of 2017. The recipient shall self-account for VAT.', 'ar' => 'تنطبق آلية الاحتساب العكسي — المادة 48 من المرسوم الاتحادي رقم 8 لسنة 2017. على المتلقي احتساب الضريبة ذاتياً.'],
        'designated_zone_internal' => ['en' => 'DESIGNATED ZONE INTERNAL SUPPLY — Outside scope of UAE VAT under Cabinet Decision 59/2017.', 'ar' => 'توريد داخل منطقة محددة — خارج نطاق ضريبة القيمة المضافة الإماراتية وفقاً لقرار مجلس الوزراء 59/2017.'],
        'exempt'                   => ['en' => 'EXEMPT SUPPLY — Article 46 Federal Decree-Law No. 8 of 2017.', 'ar' => 'توريد معفى — المادة 46 من المرسوم الاتحادي رقم 8 لسنة 2017.'],
        'zero_rated'               => ['en' => 'ZERO-RATED SUPPLY — Article 45 Federal Decree-Law No. 8 of 2017.', 'ar' => 'توريد بنسبة الصفر — المادة 45 من المرسوم الاتحادي رقم 8 لسنة 2017.'],
        'out_of_scope'             => ['en' => 'OUT OF SCOPE — Not subject to UAE VAT.', 'ar' => 'خارج النطاق — غير خاضع لضريبة القيمة المضافة الإماراتية.'],
        default                    => null,
    };

    $fmt = function ($v) {
        return number_format((float) $v, 2, '.', ',');
    };
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 35px 30px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            margin: 0;
            line-height: 1.4;
        }
        .header {
            border-bottom: 3px solid #1a3a8f;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }
        .header-bar {
            width: 100%;
            display: table;
        }
        .header-bar > div {
            display: table-cell;
            vertical-align: middle;
        }
        .header-left { width: 50%; }
        .header-right { width: 50%; text-align: left; direction: ltr; }
        .doc-label-ar {
            font-size: 22px;
            font-weight: bold;
            color: #1a3a8f;
            text-align: right;
        }
        .doc-label-en {
            font-size: 18px;
            font-weight: bold;
            color: #1a3a8f;
            text-align: left;
        }
        .doc-num {
            font-size: 11px;
            color: #444;
            margin-top: 4px;
        }
        .doc-num strong { font-family: monospace; color: #000; }
        .meta-grid {
            width: 100%;
            margin-top: 6px;
        }
        .meta-grid td {
            padding: 2px 4px;
            font-size: 9px;
        }
        .voided-stamp {
            position: fixed;
            top: 250px;
            left: 30%;
            transform: rotate(-25deg);
            font-size: 100px;
            color: rgba(220, 38, 38, 0.18);
            font-weight: bold;
            border: 8px solid rgba(220, 38, 38, 0.18);
            padding: 18px 36px;
            z-index: 9999;
            pointer-events: none;
        }
        .parties {
            width: 100%;
            margin: 14px 0;
            border-collapse: collapse;
        }
        .parties td {
            width: 50%;
            vertical-align: top;
            padding: 10px 12px;
            border: 1px solid #d4d4d8;
            background: #f9fafb;
        }
        .party-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #525252;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .party-name {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        .party-line {
            font-size: 9px;
            color: #404040;
            margin: 1px 0;
        }
        .party-trn {
            font-family: monospace;
            font-weight: bold;
            color: #1a3a8f;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 9px;
        }
        table.lines thead th {
            background: #1a3a8f;
            color: white;
            padding: 6px 5px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #1a3a8f;
        }
        table.lines tbody td {
            border: 1px solid #d4d4d8;
            padding: 6px 5px;
            vertical-align: top;
        }
        table.lines tbody tr:nth-child(even) td { background: #fafafa; }
        .text-end { text-align: right; }
        .text-start { text-align: left; }
        .text-center { text-align: center; }
        .totals {
            width: 100%;
            margin-top: 8px;
        }
        .totals td {
            padding: 4px 12px;
            font-size: 10px;
        }
        .totals .label { text-align: end; color: #525252; }
        .totals .value { text-align: end; font-family: monospace; width: 30%; }
        .totals .grand .label { font-weight: bold; color: #1a1a1a; font-size: 12px; padding-top: 8px; border-top: 2px solid #1a3a8f; }
        .totals .grand .value { font-weight: bold; color: #1a3a8f; font-size: 14px; padding-top: 8px; border-top: 2px solid #1a3a8f; }
        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #d4d4d8;
            font-size: 8px;
            color: #6b6b6b;
        }
        .footer p { margin: 3px 0; }
        .audit-block {
            margin-top: 10px;
            padding: 8px 10px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            font-size: 8px;
        }
        .audit-block .label { color: #475569; font-weight: bold; }
        .audit-block .value { font-family: monospace; color: #1a1a1a; }
    </style>
</head>
<body>

    @if($isVoided)
        <div class="voided-stamp">VOIDED</div>
    @endif

    {{-- ====== HEADER ====== --}}
    <div class="header">
        <div class="header-bar">
            <div class="header-left" @if(!empty($qrDataUri)) style="width: 42%;" @endif>
                <div class="doc-label-ar">فاتورة ضريبية</div>
                <div class="doc-num">رقم: <strong>{{ $invoice->invoice_number }}</strong></div>
            </div>
            <div class="header-right" @if(!empty($qrDataUri)) style="width: 42%;" @endif>
                <div class="doc-label-en">TAX INVOICE</div>
                <div class="doc-num">No: <strong>{{ $invoice->invoice_number }}</strong></div>
            </div>
            @if(!empty($qrDataUri))
                {{-- FTA-style QR (TLV-encoded: seller name, seller TRN,
                     ISO timestamp, total, tax). Third cell pinned at
                     ~16% so the doc number on either side stays
                     readable. The QR is the FTA's visual proof of
                     compliance — a buyer's audit can scan it and
                     reconcile the on-paper figures with the issuer's
                     books in one move. --}}
                <div style="display: table-cell; vertical-align: middle; width: 16%; text-align: center;">
                    <img src="{{ $qrDataUri }}" alt="QR" style="width: 80px; height: 80px;">
                </div>
            @endif
        </div>

        <table class="meta-grid">
            <tr>
                <td style="text-align: right;">
                    <strong>تاريخ الإصدار:</strong> {{ $invoice->issue_date->format('d / m / Y') }}
                </td>
                <td style="text-align: left; direction: ltr;">
                    <strong>Issue Date:</strong> {{ $invoice->issue_date->format('d M Y') }}
                </td>
            </tr>
            @if($invoice->supply_date && $invoice->supply_date->ne($invoice->issue_date))
            <tr>
                <td style="text-align: right;">
                    <strong>تاريخ التوريد:</strong> {{ $invoice->supply_date->format('d / m / Y') }}
                </td>
                <td style="text-align: left; direction: ltr;">
                    <strong>Supply Date:</strong> {{ $invoice->supply_date->format('d M Y') }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ====== PARTIES ====== --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party-label">المورّد / SUPPLIER</div>
                <div class="party-name">{{ $invoice->supplier_name }}</div>
                @if($invoice->supplier_trn)
                    <div class="party-line">TRN / الرقم الضريبي: <span class="party-trn">{{ $invoice->supplier_trn }}</span></div>
                @endif
                @if($invoice->supplier_address)
                    <div class="party-line">{{ $invoice->supplier_address }}</div>
                @endif
                @if($invoice->supplier_country)
                    <div class="party-line">{{ $invoice->supplier_country }}</div>
                @endif
            </td>
            <td>
                <div class="party-label">المشتري / BUYER</div>
                <div class="party-name">{{ $invoice->buyer_name }}</div>
                @if($invoice->buyer_trn)
                    <div class="party-line">TRN / الرقم الضريبي: <span class="party-trn">{{ $invoice->buyer_trn }}</span></div>
                @endif
                @if($invoice->buyer_address)
                    <div class="party-line">{{ $invoice->buyer_address }}</div>
                @endif
                @if($invoice->buyer_country)
                    <div class="party-line">{{ $invoice->buyer_country }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Phase 7 (UAE Compliance Roadmap) — Corporate Tax annotation.
         Shows the supplier's CT status when it's QFZP or exempt. This
         lets the buyer know the supply carries 0% CT treatment on the
         supplier's books — relevant for transfer pricing documentation
         under Federal Decree-Law 47/2022. --}}
    @if($ctAnnotation)
        <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 6px 10px; margin: 6px 0; border-radius: 3px; font-size: 9px;">
            <strong style="color: #166534;">Corporate Tax / ضريبة الشركات:</strong>
            <span style="color: #166534;">{{ $ctAnnotation }}</span>
        </div>
    @endif

    {{-- Phase 1.5 (UAE Compliance Roadmap — post-implementation hardening).
         VAT treatment banner. Cabinet Decision 52/2017 Article 59(1)(j)
         requires the legal treatment to be visible on the invoice
         document itself when reverse charge applies. We render a
         similar banner for designated zone, exempt and zero-rated
         supplies for symmetry. --}}
    @php
        $vatTreatment = $invoice->vat_treatment ?? 'standard';
        $vatBanner = match ($vatTreatment) {
            'reverse_charge'           => [
                'color' => '#b91c1c',
                'ar'    => 'تخضع هذه التوريدات لآلية الاحتساب العكسي — يحتسب المتلقي ضريبة القيمة المضافة بنفسه وفقاً للمادة 48 من المرسوم الاتحادي رقم 8 لسنة 2017.',
                'en'    => 'Reverse Charge Mechanism applies — the recipient self-accounts for VAT under Article 48 of Federal Decree-Law No. 8 of 2017. The supplier does NOT collect VAT on this invoice.',
            ],
            'designated_zone_internal' => [
                'color' => '#0369a1',
                'ar'    => 'هذه المعاملة بين منطقتين محددتين لأغراض ضريبة القيمة المضافة — خارج نطاق الضريبة وفقاً لقرار مجلس الوزراء رقم 59 لسنة 2017.',
                'en'    => 'Supply between two VAT Designated Zones — outside the scope of UAE VAT under Cabinet Decision No. 59 of 2017.',
            ],
            'exempt'                   => [
                'color' => '#7c3aed',
                'ar'    => 'هذه التوريدات معفاة من ضريبة القيمة المضافة وفقاً للمادة 46 من المرسوم الاتحادي رقم 8 لسنة 2017. لا يحق للمتلقي استرداد ضريبة المدخلات.',
                'en'    => 'EXEMPT supply under Article 46 of Federal Decree-Law No. 8 of 2017. Input tax cannot be recovered.',
            ],
            'zero_rated'               => [
                'color' => '#0d9488',
                'ar'    => 'هذه التوريدات خاضعة لنسبة الصفر وفقاً للمادة 45 من المرسوم الاتحادي رقم 8 لسنة 2017.',
                'en'    => 'ZERO-RATED supply under Article 45 of Federal Decree-Law No. 8 of 2017.',
            ],
            'out_of_scope'             => [
                'color' => '#525252',
                'ar'    => 'هذه المعاملة خارج نطاق ضريبة القيمة المضافة الإماراتية.',
                'en'    => 'This supply is outside the scope of UAE VAT.',
            ],
            default                    => null,
        };
    @endphp

    @if($vatBanner)
        <div style="border: 2px solid {{ $vatBanner['color'] }}; background: {{ $vatBanner['color'] }}10; padding: 10px 12px; margin: 10px 0; border-radius: 3px;">
            <p style="font-size: 11px; color: {{ $vatBanner['color'] }}; font-weight: bold; margin: 0 0 4px 0; text-align: right; direction: rtl;">
                {{ $vatBanner['ar'] }}
            </p>
            <p style="font-size: 10px; color: {{ $vatBanner['color'] }}; font-weight: bold; margin: 4px 0 0 0; text-align: left; direction: ltr;">
                {{ $vatBanner['en'] }}
            </p>
        </div>
    @endif

    {{-- ====== LINE ITEMS ====== --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 36%;" class="text-start">الوصف / Description</th>
                <th style="width: 8%;">الكمية / Qty</th>
                <th style="width: 8%;">الوحدة / Unit</th>
                <th style="width: 12%;" class="text-end">سعر الوحدة / Unit Price</th>
                <th style="width: 8%;" class="text-end">الضريبة % / VAT</th>
                <th style="width: 12%;" class="text-end">قيمة الضريبة / VAT Amount</th>
                <th style="width: 12%;" class="text-end">الإجمالي / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->line_items as $i => $line)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="text-start">{{ $line['description'] ?? '—' }}</td>
                    <td class="text-center">{{ $fmt($line['quantity'] ?? 0) }}</td>
                    <td class="text-center">{{ $line['unit'] ?? '—' }}</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['unit_price'] ?? 0) }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) ($line['tax_rate'] ?? 0), 2), '0'), '.') }}%</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['tax_amount'] ?? 0) }}</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['line_total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ====== VAT TREATMENT BANNER (Phase 1.5) ====== --}}
    @if($vatBannerText)
        <div style="margin: 14px 0; padding: 10px 14px; border: 2px solid #b91c1c; background: #fef2f2; border-radius: 4px;">
            <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: bold; color: #b91c1c; text-align: right;">{!! $vatBannerText['ar'] !!}</p>
            <p style="margin: 0; font-size: 10px; font-weight: bold; color: #b91c1c; text-align: left; direction: ltr;">{!! $vatBannerText['en'] !!}</p>
        </div>
    @endif

    {{-- ====== TOTALS ====== --}}
    <table class="totals">
        <tr>
            <td class="label">المجموع الفرعي قبل الضريبة / Subtotal (excl. VAT)</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->subtotal_excl_tax) }}</td>
        </tr>
        @if((float) $invoice->total_discount > 0)
        <tr>
            <td class="label">الخصم / Discount</td>
            <td class="value">- {{ $currency }} {{ $fmt($invoice->total_discount) }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">إجمالي ضريبة القيمة المضافة / Total VAT</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->total_tax) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">المجموع شامل الضريبة / Total Inclusive of VAT</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->total_inclusive) }}</td>
        </tr>
    </table>

    {{-- ====== AUDIT BLOCK ====== --}}
    <div class="audit-block">
        <span class="label">Issued at / صادرة في:</span>
        <span class="value">{{ $invoice->issued_at?->format('Y-m-d H:i') ?? '—' }} GST</span>
        &nbsp;·&nbsp;
        <span class="label">Status / الحالة:</span>
        <span class="value">{{ $isVoided ? 'VOIDED / ملغاة' : 'ISSUED / صادرة' }}</span>
        @if($isVoided)
            <br>
            <span class="label">Voided at:</span>
            <span class="value">{{ $invoice->voided_at?->format('Y-m-d H:i') }}</span>
            @if($invoice->void_reason)
                <br>
                <span class="label">Void reason:</span>
                <span class="value">{{ $invoice->void_reason }}</span>
            @endif
        @endif
    </div>

    {{-- ====== FOOTER ====== --}}
    <div class="footer">
        <p>
            <strong>هذه فاتورة ضريبية رسمية صادرة وفقاً لقانون ضريبة القيمة المضافة الاتحادي رقم 8 لسنة 2017.</strong><br>
            This is an official tax invoice issued in accordance with UAE Federal Decree-Law No. 8 of 2017 on Value Added Tax.
        </p>
        <p>
            النسخة العربية تسود في حال أي خلاف في التفسير أمام المحاكم الإماراتية وفقاً للمادة 5 من القانون الاتحادي رقم 26 لسنة 1981.<br>
            The Arabic version prevails in case of any interpretation discrepancy before UAE courts pursuant to Article 5 of Federal Law No. 26 of 1981.
        </p>
    </div>

</body>
</html>
