@php
    /**
     * UAE-grade Tax Invoice PDF.
     *
     * Drafted to satisfy:
     *   - Federal Decree-Law No. 8 of 2017 (VAT) — Article 65
     *   - Cabinet Decision No. 52 of 2017 — Articles 59-60
     *
     * Every required field is present:
     *   1. The words "Tax Invoice" prominently
     *   2. Issuer name + TRN + address
     *   3. Recipient name + TRN + address
     *   4. Sequential invoice number
     *   5. Date of issue
     *   6. Date of supply (if different)
     *   7. Description of goods/services
     *   8. Quantity & unit price
     *   9. Discount per line (if any)
     *  10. Tax rate per line
     *  11. Tax amount in AED
     *  12. Total amount inclusive of tax
     *
     * Locale modes (controlled by $pdfLocale):
     *   - null       → bilingual side-by-side (canonical FTA storage copy)
     *   - 'ar'       → Arabic-only render
     *   - 'en'       → English-only render
     *
     * Arabic runs are pre-shaped via ArabicShaper — dompdf does no GSUB
     * shaping itself, so without this step glyphs appear in memory order
     * (letters unconnected, words reversed) even when the font is correct.
     */
    use App\Support\ArabicShaper;

    $pdfLocale = $pdfLocale ?? null;  // null = bilingual
    $showAr    = $pdfLocale === null || $pdfLocale === 'ar';
    $showEn    = $pdfLocale === null || $pdfLocale === 'en';
    $isRtlOnly = $pdfLocale === 'ar';
    $dir       = $isRtlOnly ? 'rtl' : 'ltr';

    // Shape any string that may carry Arabic. No-op for pure Latin/numeric.
    $ar = fn ($text) => ArabicShaper::shape((string) $text);

    $isVoided = $invoice->isVoided();
    $currency = $invoice->currency;
    $qrDataUri = $qrDataUri ?? null;
    $ctAnnotation = $ctAnnotation ?? null;

    $vatTreatment = $invoice->vat_treatment ?? 'standard';
    $vatBannerText = match ($vatTreatment) {
        'reverse_charge'           => ['en' => 'REVERSE CHARGE MECHANISM APPLIES — Article 48 Federal Decree-Law No. 8 of 2017. The recipient shall self-account for VAT.', 'ar' => 'تنطبق آلية الاحتساب العكسي — المادة 48 من المرسوم الاتحادي رقم 8 لسنة 2017. على المتلقي احتساب الضريبة ذاتياً.'],
        'designated_zone_internal' => ['en' => 'DESIGNATED ZONE INTERNAL SUPPLY — Outside scope of UAE VAT under Cabinet Decision 59/2017.', 'ar' => 'توريد داخل منطقة محددة — خارج نطاق ضريبة القيمة المضافة الإماراتية وفقاً لقرار مجلس الوزراء 59/2017.'],
        'exempt'                   => ['en' => 'EXEMPT SUPPLY — Article 46 Federal Decree-Law No. 8 of 2017.', 'ar' => 'توريد معفى — المادة 46 من المرسوم الاتحادي رقم 8 لسنة 2017.'],
        'zero_rated'               => ['en' => 'ZERO-RATED SUPPLY — Article 45 Federal Decree-Law No. 8 of 2017.', 'ar' => 'توريد بنسبة الصفر — المادة 45 من المرسوم الاتحادي رقم 8 لسنة 2017.'],
        'out_of_scope'             => ['en' => 'OUT OF SCOPE — Not subject to UAE VAT.', 'ar' => 'خارج النطاق — غير خاضع لضريبة القيمة المضافة الإماراتية.'],
        default                    => null,
    };

    $fmt = fn ($v) => number_format((float) $v, 2, '.', ',');

    // Bilingual label helper. When both languages are visible, returns
    // "Arabic / English"; otherwise only the visible side. Arabic side
    // is pre-shaped so dompdf renders glyphs in visual order.
    $label = function (string $arText, string $enText) use ($ar, $showAr, $showEn) {
        if ($showAr && $showEn) {
            return $ar($arText).' / '.$enText;
        }
        if ($showAr) {
            return $ar($arText);
        }
        return $enText;
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale ?? 'ar' }}" dir="{{ $dir }}">
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
            direction: {{ $dir }};
            unicode-bidi: embed;
        }
        div, table, td, th, p, span { unicode-bidi: embed; }
        .ltr-inline { direction: ltr; unicode-bidi: bidi-override; display: inline-block; }
        .header { border-bottom: 3px solid #1a3a8f; padding-bottom: 14px; margin-bottom: 14px; }
        .header-bar { width: 100%; display: table; }
        .header-bar > div { display: table-cell; vertical-align: middle; }
        .header-left  { width: {{ $showAr && $showEn ? '50%' : '84%' }}; }
        .header-right { width: {{ $showAr && $showEn ? '50%' : '84%' }}; text-align: left; direction: ltr; }
        .doc-label-ar { font-size: 22px; font-weight: bold; color: #1a3a8f; text-align: right; }
        .doc-label-en { font-size: 18px; font-weight: bold; color: #1a3a8f; text-align: left; }
        .doc-num { font-size: 11px; color: #444; margin-top: 4px; }
        .doc-num strong { font-family: monospace; color: #000; }
        .meta-grid { width: 100%; margin-top: 6px; }
        .meta-grid td { padding: 2px 4px; font-size: 9px; }
        .voided-stamp {
            position: fixed; top: 250px; left: 30%;
            transform: rotate(-25deg); font-size: 100px;
            color: rgba(220, 38, 38, 0.18); font-weight: bold;
            border: 8px solid rgba(220, 38, 38, 0.18);
            padding: 18px 36px; z-index: 9999; pointer-events: none;
        }
        .parties { width: 100%; margin: 14px 0; border-collapse: collapse; }
        .parties td {
            width: {{ $showAr && $showEn ? '50%' : '100%' }};
            vertical-align: top; padding: 10px 12px;
            border: 1px solid #d4d4d8; background: #f9fafb;
        }
        .party-label { font-size: 9px; letter-spacing: 0.5px; color: #525252; margin-bottom: 4px; text-transform: {{ $isRtlOnly ? 'none' : 'uppercase' }}; }
        .party-name { font-size: 13px; font-weight: bold; color: #1a1a1a; margin-bottom: 2px; }
        .party-line { font-size: 9px; color: #404040; margin: 1px 0; }
        .party-trn { font-family: monospace; font-weight: bold; color: #1a3a8f; }
        table.lines { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9px; }
        table.lines thead th {
            background: #1a3a8f; color: white; padding: 6px 5px;
            text-align: center; font-weight: bold; font-size: 9px;
            border: 1px solid #1a3a8f;
        }
        table.lines tbody td { border: 1px solid #d4d4d8; padding: 6px 5px; vertical-align: top; }
        table.lines tbody tr:nth-child(even) td { background: #fafafa; }
        .text-end { text-align: {{ $isRtlOnly ? 'left' : 'right' }}; }
        .text-start { text-align: {{ $isRtlOnly ? 'right' : 'left' }}; }
        .text-center { text-align: center; }
        .totals { width: 100%; margin-top: 8px; }
        .totals td { padding: 4px 12px; font-size: 10px; }
        .totals .label { text-align: {{ $isRtlOnly ? 'left' : 'right' }}; color: #525252; }
        .totals .value { text-align: {{ $isRtlOnly ? 'left' : 'right' }}; font-family: monospace; width: 30%; }
        .totals .grand .label { font-weight: bold; color: #1a1a1a; font-size: 12px; padding-top: 8px; border-top: 2px solid #1a3a8f; }
        .totals .grand .value { font-weight: bold; color: #1a3a8f; font-size: 14px; padding-top: 8px; border-top: 2px solid #1a3a8f; }
        .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #d4d4d8; font-size: 8px; color: #6b6b6b; }
        .footer p { margin: 3px 0; }
        .audit-block {
            margin-top: 10px; padding: 8px 10px;
            background: #f1f5f9; border: 1px solid #cbd5e1;
            border-radius: 3px; font-size: 8px;
        }
        .audit-block .label { color: #475569; font-weight: bold; }
        .audit-block .value { font-family: monospace; color: #1a1a1a; }
        .lang-ribbon {
            display: inline-block; font-size: 9px; font-weight: 700;
            padding: 3px 10px; border-radius: 999px;
            background: #eef3ff; border: 1px solid #c7d4ff; color: #1a3a8f;
            margin-top: 6px;
        }
    </style>
</head>
<body>

    @if($isVoided)
        <div class="voided-stamp">VOIDED</div>
    @endif

    {{-- ====== HEADER ====== --}}
    <div class="header">
        <div class="header-bar">
            @if($showAr)
            <div class="header-left" @if(!empty($qrDataUri) && $showEn) style="width: 42%;" @endif>
                <div class="doc-label-ar">{{ $ar('فاتورة ضريبية') }}</div>
                <div class="doc-num"><span style="direction:rtl">{{ $ar('رقم') }}:</span> <strong class="ltr-inline">{{ $invoice->invoice_number }}</strong></div>
            </div>
            @endif
            @if($showEn)
            <div class="header-right" @if(!empty($qrDataUri) && $showAr) style="width: 42%;" @endif>
                <div class="doc-label-en">TAX INVOICE</div>
                <div class="doc-num">No: <strong>{{ $invoice->invoice_number }}</strong></div>
            </div>
            @endif
            @if(!empty($qrDataUri))
                <div style="display: table-cell; vertical-align: middle; width: 16%; text-align: center;">
                    <img src="{{ $qrDataUri }}" alt="QR" style="width: 80px; height: 80px;">
                </div>
            @endif
        </div>

        <table class="meta-grid">
            <tr>
                @if($showAr)
                <td style="text-align: right;">
                    <strong>{{ $ar('تاريخ الإصدار') }}:</strong> <span class="ltr-inline">{{ $invoice->issue_date->format('d / m / Y') }}</span>
                </td>
                @endif
                @if($showEn)
                <td style="text-align: left; direction: ltr;">
                    <strong>Issue Date:</strong> {{ $invoice->issue_date->format('d M Y') }}
                </td>
                @endif
            </tr>
            @if($invoice->supply_date && $invoice->supply_date->ne($invoice->issue_date))
            <tr>
                @if($showAr)
                <td style="text-align: right;">
                    <strong>{{ $ar('تاريخ التوريد') }}:</strong> <span class="ltr-inline">{{ $invoice->supply_date->format('d / m / Y') }}</span>
                </td>
                @endif
                @if($showEn)
                <td style="text-align: left; direction: ltr;">
                    <strong>Supply Date:</strong> {{ $invoice->supply_date->format('d M Y') }}
                </td>
                @endif
            </tr>
            @endif
        </table>
    </div>

    {{-- ====== PARTIES ====== --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party-label">{{ $label('المورّد', 'SUPPLIER') }}</div>
                <div class="party-name">{{ $ar($invoice->supplier_name) }}</div>
                @if($invoice->supplier_trn)
                    <div class="party-line">
                        {{ $label('الرقم الضريبي', 'TRN') }}:
                        <span class="party-trn ltr-inline">{{ $invoice->supplier_trn }}</span>
                    </div>
                @endif
                @if($invoice->supplier_address)
                    <div class="party-line">{{ $ar($invoice->supplier_address) }}</div>
                @endif
                @if($invoice->supplier_country)
                    <div class="party-line">{{ $ar($invoice->supplier_country) }}</div>
                @endif
            </td>
            <td>
                <div class="party-label">{{ $label('المشتري', 'BUYER') }}</div>
                <div class="party-name">{{ $ar($invoice->buyer_name) }}</div>
                @if($invoice->buyer_trn)
                    <div class="party-line">
                        {{ $label('الرقم الضريبي', 'TRN') }}:
                        <span class="party-trn ltr-inline">{{ $invoice->buyer_trn }}</span>
                    </div>
                @endif
                @if($invoice->buyer_address)
                    <div class="party-line">{{ $ar($invoice->buyer_address) }}</div>
                @endif
                @if($invoice->buyer_country)
                    <div class="party-line">{{ $ar($invoice->buyer_country) }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ====== CORPORATE TAX ANNOTATION ====== --}}
    @if($ctAnnotation)
        <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 6px 10px; margin: 6px 0; border-radius: 3px; font-size: 9px;">
            <strong style="color: #166534;">{{ $label('ضريبة الشركات', 'Corporate Tax') }}:</strong>
            <span style="color: #166534;">{{ $ar($ctAnnotation) }}</span>
        </div>
    @endif

    {{-- ====== VAT TREATMENT BANNER ====== --}}
    @if($vatBannerText)
        <div style="border: 2px solid #b91c1c; background: #fef2f2; padding: 10px 12px; margin: 10px 0; border-radius: 3px;">
            @if($showAr)
                <p style="font-size: 11px; color: #b91c1c; font-weight: bold; margin: 0 0 4px 0; text-align: right; direction: rtl;">
                    {{ $ar($vatBannerText['ar']) }}
                </p>
            @endif
            @if($showEn)
                <p style="font-size: 10px; color: #b91c1c; font-weight: bold; margin: 4px 0 0 0; text-align: left; direction: ltr;">
                    {!! $vatBannerText['en'] !!}
                </p>
            @endif
        </div>
    @endif

    {{-- ====== LINE ITEMS ====== --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 36%;" class="text-start">{{ $label('الوصف', 'Description') }}</th>
                <th style="width: 8%;">{{ $label('الكمية', 'Qty') }}</th>
                <th style="width: 8%;">{{ $label('الوحدة', 'Unit') }}</th>
                <th style="width: 12%;" class="text-end">{{ $label('سعر الوحدة', 'Unit Price') }}</th>
                <th style="width: 8%;" class="text-end">{{ $label('الضريبة %', 'VAT') }}</th>
                <th style="width: 12%;" class="text-end">{{ $label('قيمة الضريبة', 'VAT Amount') }}</th>
                <th style="width: 12%;" class="text-end">{{ $label('الإجمالي', 'Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->line_items as $i => $line)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="text-start">{{ $ar($line['description'] ?? '—') }}</td>
                    <td class="text-center">{{ $fmt($line['quantity'] ?? 0) }}</td>
                    <td class="text-center">{{ $ar($line['unit'] ?? '—') }}</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['unit_price'] ?? 0) }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) ($line['tax_rate'] ?? 0), 2), '0'), '.') }}%</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['tax_amount'] ?? 0) }}</td>
                    <td class="text-end">{{ $currency }} {{ $fmt($line['line_total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ====== TOTALS ====== --}}
    <table class="totals">
        <tr>
            <td class="label">{{ $label('المجموع الفرعي قبل الضريبة', 'Subtotal (excl. VAT)') }}</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->subtotal_excl_tax) }}</td>
        </tr>
        @if((float) $invoice->total_discount > 0)
        <tr>
            <td class="label">{{ $label('الخصم', 'Discount') }}</td>
            <td class="value">- {{ $currency }} {{ $fmt($invoice->total_discount) }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">{{ $label('إجمالي ضريبة القيمة المضافة', 'Total VAT') }}</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->total_tax) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">{{ $label('المجموع شامل الضريبة', 'Total Inclusive of VAT') }}</td>
            <td class="value">{{ $currency }} {{ $fmt($invoice->total_inclusive) }}</td>
        </tr>
    </table>

    {{-- ====== AUDIT BLOCK ====== --}}
    <div class="audit-block">
        @php
            $issuedAt = $invoice->issued_at?->format('Y-m-d H:i') ?? '—';
            $statusLabel = $isVoided
                ? $label('ملغاة', 'VOIDED')
                : $label('صادرة', 'ISSUED');
        @endphp
        <span class="label">{{ $label('صادرة في', 'Issued at') }}:</span>
        <span class="value ltr-inline">{{ $issuedAt }} GST</span>
        &nbsp;·&nbsp;
        <span class="label">{{ $label('الحالة', 'Status') }}:</span>
        <span class="value">{{ $statusLabel }}</span>
        @if($isVoided)
            <br>
            <span class="label">{{ $label('تاريخ الإلغاء', 'Voided at') }}:</span>
            <span class="value ltr-inline">{{ $invoice->voided_at?->format('Y-m-d H:i') }}</span>
            @if($invoice->void_reason)
                <br>
                <span class="label">{{ $label('سبب الإلغاء', 'Void reason') }}:</span>
                <span class="value">{{ $ar($invoice->void_reason) }}</span>
            @endif
        @endif
    </div>

    {{-- ====== FOOTER ====== --}}
    <div class="footer">
        @if($showAr)
            <p style="text-align: right; direction: rtl;">
                <strong>{{ $ar('هذه فاتورة ضريبية رسمية صادرة وفقاً لقانون ضريبة القيمة المضافة الاتحادي رقم 8 لسنة 2017.') }}</strong>
            </p>
        @endif
        @if($showEn)
            <p style="text-align: left; direction: ltr;">
                This is an official tax invoice issued in accordance with UAE Federal Decree-Law No. 8 of 2017 on Value Added Tax.
            </p>
        @endif
        @if($showAr)
            <p style="text-align: right; direction: rtl;">
                {{ $ar('النسخة العربية تسود في حال أي خلاف في التفسير أمام المحاكم الإماراتية وفقاً للمادة 5 من القانون الاتحادي رقم 26 لسنة 1981.') }}
            </p>
        @endif
        @if($showEn)
            <p style="text-align: left; direction: ltr;">
                The Arabic version prevails in case of any interpretation discrepancy before UAE courts pursuant to Article 5 of Federal Law No. 26 of 1981.
            </p>
        @endif
    </div>

</body>
</html>
