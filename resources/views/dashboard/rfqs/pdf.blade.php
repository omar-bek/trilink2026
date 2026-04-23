@php
    /**
     * Bilingual RFQ package PDF. Locale is resolved from $pdfLocale
     * (preferred) or app()->getLocale() and drives direction + shaping.
     * Arabic runs are pre-shaped via ArabicShaper because dompdf does
     * no GSUB shaping of its own.
     */
    use App\Support\ArabicShaper;

    $locale    = $pdfLocale ?? app()->getLocale();
    $isRtl     = $locale === 'ar';
    $dir       = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';
    $bodyFont  = 'Arial, sans-serif';

    $ar = fn ($text) => ArabicShaper::shape((string) $text);
    $rtlCells = fn (array $cells) => $isRtl ? array_reverse($cells) : $cells;

    $loc = $rfq->delivery_location;
    if (is_array($loc)) {
        $loc = trim(implode(', ', array_filter([
            $loc['address'] ?? null,
            $loc['city'] ?? null,
            $loc['country'] ?? null,
        ]))) ?: '—';
    }

    $buyerName = $rfq->is_anonymous
        ? __('rfq.pdf_anonymous_buyer')
        : ($rfq->company?->name ?? '—');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('rfq.pdf_title') }} — {{ $rfq->rfq_number }}</title>
    <style>
        @page { margin: 40px 32px; }
        body {
            font-family: {{ $bodyFont }};
            font-size: 11px;
            color: #2d3548;
            line-height: {{ $isRtl ? '1.75' : '1.55' }};
            direction: {{ $dir }};
            unicode-bidi: embed;
        }
        div, table, td, th, p, span, h1, h2 {
            direction: {{ $dir }};
            unicode-bidi: embed;
        }
        .ltr-inline { direction: ltr; unicode-bidi: bidi-override; display: inline-block; }

        .doc-header {
            border-bottom: 2px solid #0f1117;
            padding-bottom: 12px;
            margin-bottom: 18px;
            text-align: center;
        }
        .brand {
            font-size: 10px;
            color: #4f7cff;
            font-weight: 700;
            letter-spacing: {{ $isRtl ? '0' : '0.18em' }};
            text-transform: {{ $isRtl ? 'none' : 'uppercase' }};
            margin-bottom: 4px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f1117;
            margin: 0 0 4px 0;
        }
        .subtitle {
            font-size: 10.5px;
            color: #4f5366;
            margin: 0 0 8px 0;
        }
        .lang-ribbon {
            display: inline-block;
            font-size: {{ $isRtl ? '11px' : '9px' }};
            font-weight: 700;
            color: #4f7cff;
            background: rgba(79,124,255,0.08);
            border: 1px solid rgba(79,124,255,0.25);
            border-radius: 999px;
            padding: 3px 12px;
            margin-top: 6px;
        }
        .doc-num {
            font-size: 11px;
            color: #444;
            margin-top: 6px;
        }
        .doc-num strong { font-family: monospace; color: #000; }

        .section { margin-bottom: 16px; }
        .section-title {
            font-weight: bold;
            font-size: 13px;
            color: #4f7cff;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
            text-align: {{ $textAlign }};
        }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 4px 6px;
            text-align: {{ $textAlign }};
        }
        .label {
            color: #6b7280;
            font-size: 10px;
            letter-spacing: {{ $isRtl ? '0' : '0.5px' }};
            text-transform: {{ $isRtl ? 'none' : 'uppercase' }};
        }
        .value { font-weight: 600; font-size: 12px; margin-top: 2px; }
        .amount { color: #00d9b5; font-weight: 700; font-size: 18px; }

        table.lines { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.lines th, table.lines td {
            border: 1px solid #e5e7eb;
            padding: 7px 8px;
            text-align: {{ $textAlign }};
            font-size: 10.5px;
        }
        table.lines th {
            background-color: #f9fafb;
            color: #4f7cff;
            font-size: 10px;
        }
        table.lines td.num { text-align: center; }
        table.lines td.qty { text-align: {{ $isRtl ? 'left' : 'right' }}; font-family: monospace; }

        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #d8dce6;
            text-align: center;
            color: #8b8f9c;
            font-size: 8.5px;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <div class="doc-header">
        <div class="brand">TriLink Trading Platform</div>
        <h1>{{ $ar(__('rfq.pdf_title')) }}</h1>
        <div class="subtitle">{{ $ar(__('rfq.pdf_subtitle')) }}</div>
        <span class="lang-ribbon">
            {{ $ar($isRtl ? __('rfq.pdf_ribbon_ar') : __('rfq.pdf_ribbon_en')) }}
            ·
            {{ $ar($isRtl ? __('rfq.pdf_ribbon_note_ar') : __('rfq.pdf_ribbon_note_en')) }}
        </span>
        <div class="doc-num">
            <strong class="ltr-inline">{{ $rfq->rfq_number }}</strong>
            &middot;
            <span class="ltr-inline">{{ $rfq->created_at?->format('d / m / Y') }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $ar(__('rfq.pdf_project_title')) }}</div>
        <p class="value">{{ $ar($rfq->title) }}</p>
    </div>

    @if($rfq->description)
    <div class="section">
        <div class="section-title">{{ $ar(__('rfq.pdf_description')) }}</div>
        <p>{{ $ar($rfq->description) }}</p>
    </div>
    @endif

    <div class="section">
        <div class="section-title">{{ $ar(__('rfq.pdf_summary')) }}</div>
        <table class="grid">
            @php
                $row1 = $rtlCells([
                    ['label' => __('rfq.pdf_buyer'),    'value' => $buyerName],
                    ['label' => __('rfq.pdf_category'), 'value' => $rfq->category?->name ?? '—'],
                ]);
                $row2 = $rtlCells([
                    ['label' => __('rfq.pdf_budget'),   'value' => ($rfq->currency ?? 'AED') . ' ' . number_format((float) $rfq->budget, 2), 'amount' => true],
                    ['label' => __('rfq.pdf_deadline'), 'value' => optional($rfq->deadline)->format('d / m / Y') ?? '—'],
                ]);
            @endphp
            <tr>
                @foreach($row1 as $cell)
                    <td>
                        <p class="label">{{ $ar($cell['label']) }}</p>
                        <p class="value">{{ $ar($cell['value']) }}</p>
                    </td>
                @endforeach
            </tr>
            <tr>
                @foreach($row2 as $cell)
                    <td>
                        <p class="label">{{ $ar($cell['label']) }}</p>
                        @if($cell['amount'] ?? false)
                            <p class="amount"><span class="ltr-inline">{{ $cell['value'] }}</span></p>
                        @else
                            <p class="value"><span class="ltr-inline">{{ $cell['value'] }}</span></p>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    @if(!empty($rfq->items))
    <div class="section">
        <div class="section-title">{{ $ar(__('rfq.pdf_line_items')) }}</div>
        <table class="lines">
            <thead>
                <tr>
                    @php
                        $headers = $rtlCells([
                            '#',
                            $ar(__('rfq.pdf_item')),
                            $ar(__('rfq.pdf_specification')),
                            $ar(__('rfq.pdf_quantity')),
                            $ar(__('rfq.pdf_unit')),
                        ]);
                    @endphp
                    @foreach($headers as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rfq->items as $i => $item)
                    @php
                        $cells = $rtlCells([
                            ['cls' => 'num', 'val' => $i + 1],
                            ['cls' => '',    'val' => $ar($item['name'] ?? '—')],
                            ['cls' => '',    'val' => $ar($item['spec'] ?? $item['description'] ?? '—')],
                            ['cls' => 'qty', 'val' => number_format((float) ($item['qty'] ?? $item['quantity'] ?? 0))],
                            ['cls' => '',    'val' => $ar($item['unit'] ?? '—')],
                        ]);
                    @endphp
                    <tr>
                        @foreach($cells as $cell)
                            <td class="{{ $cell['cls'] }}">{{ $cell['val'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div class="section-title">{{ $ar(__('rfq.pdf_delivery')) }}</div>
        <p class="value">{{ $ar($loc ?: '—') }}</p>
    </div>

    <div class="footer">
        {{ $ar(__('rfq.pdf_generated_on', ['date' => now()->format('d / m / Y · H:i') . ' UTC'])) }}<br>
        {{ $ar(__('rfq.pdf_footer')) }}
    </div>

</body>
</html>
