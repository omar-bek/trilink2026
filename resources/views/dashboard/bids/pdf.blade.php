@php
    /**
     * Bilingual Bid proposal PDF. Locale resolves from $pdfLocale →
     * app()->getLocale(). Arabic runs are pre-shaped via ArabicShaper
     * because dompdf does no GSUB shaping of its own.
     */
    use App\Support\ArabicShaper;

    $locale    = $pdfLocale ?? app()->getLocale();
    $isRtl     = $locale === 'ar';
    $dir       = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';
    $bodyFont  = 'Arial, sans-serif';

    $ar = fn ($text) => ArabicShaper::shape((string) $text);
    $rtlCells = fn (array $cells) => $isRtl ? array_reverse($cells) : $cells;

    $bidNumber = 'BID-' . ($bid->created_at?->format('Y') ?? date('Y'))
        . '-' . str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT);
    $currency = $bid->currency ?? 'AED';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('bid.pdf_title') }} — {{ $bidNumber }}</title>
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
        .amount { color: #00d9b5; font-weight: 700; font-size: 20px; }

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
        table.lines td.num  { text-align: {{ $isRtl ? 'left' : 'right' }}; font-family: monospace; }

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
        <h1>{{ $ar(__('bid.pdf_title')) }}</h1>
        <div class="subtitle">{{ $ar(__('bid.pdf_subtitle')) }}</div>
        <span class="lang-ribbon">
            {{ $ar($isRtl ? __('bid.pdf_ribbon_ar') : __('bid.pdf_ribbon_en')) }}
        </span>
        <div class="doc-num">
            <strong class="ltr-inline">{{ $bidNumber }}</strong>
            &middot;
            <span class="ltr-inline">{{ $bid->created_at?->format('d / m / Y') }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_rfq_reference')) }}</div>
        <table class="grid">
            @php
                $refRow = $rtlCells([
                    ['label' => __('bid.pdf_rfq_number'), 'value' => '#' . ($bid->rfq?->rfq_number ?? '—'), 'ltr' => true],
                    ['label' => __('bid.pdf_category'),   'value' => $bid->rfq?->category?->name ?? '—'],
                ]);
            @endphp
            <tr>
                @foreach($refRow as $cell)
                    <td>
                        <p class="label">{{ $ar($cell['label']) }}</p>
                        @if($cell['ltr'] ?? false)
                            <p class="value"><span class="ltr-inline">{{ $cell['value'] }}</span></p>
                        @else
                            <p class="value">{{ $ar($cell['value']) }}</p>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
        <div style="margin-top: 6px;">
            <p class="label">{{ $ar(__('bid.pdf_project')) }}</p>
            <p class="value">{{ $ar($bid->rfq?->title ?? '—') }}</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_bid_amount')) }}</div>
        <p class="amount"><span class="ltr-inline">{{ $currency }} {{ number_format((float) $bid->price, 2) }}</span></p>
    </div>

    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_terms')) }}</div>
        <table class="grid">
            @php
                $termsRow = $rtlCells([
                    [
                        'label' => __('bid.pdf_delivery_time'),
                        'value' => __('bid.pdf_delivery_days', ['days' => (int) ($bid->delivery_time_days ?? 0)]),
                    ],
                    [
                        'label' => __('bid.pdf_valid_until'),
                        'value' => optional($bid->validity_date)->format('d / m / Y') ?? '—',
                        'ltr'   => true,
                    ],
                ]);
            @endphp
            <tr>
                @foreach($termsRow as $cell)
                    <td>
                        <p class="label">{{ $ar($cell['label']) }}</p>
                        @if($cell['ltr'] ?? false)
                            <p class="value"><span class="ltr-inline">{{ $cell['value'] }}</span></p>
                        @else
                            <p class="value">{{ $ar($cell['value']) }}</p>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
        <div style="margin-top: 6px;">
            <p class="label">{{ $ar(__('bid.pdf_payment_terms')) }}</p>
            <p class="value">{{ $ar($bid->payment_terms ?? '—') }}</p>
        </div>
    </div>

    @if(!empty($schedule))
    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_payment_schedule')) }}</div>
        <table class="lines">
            <thead>
                <tr>
                    @php
                        $schHeaders = $rtlCells([
                            $ar(__('bid.pdf_milestone')),
                            $ar(__('bid.pdf_percentage')),
                            $ar(__('bid.pdf_amount')),
                        ]);
                    @endphp
                    @foreach($schHeaders as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($schedule as $row)
                    @php
                        $cells = $rtlCells([
                            ['cls' => '',    'val' => $ar($row['milestone'])],
                            ['cls' => 'num', 'val' => $row['percentage'] . '%'],
                            ['cls' => 'num', 'val' => $row['amount']],
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

    @if(!empty($bid->items))
    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_line_items')) }}</div>
        <table class="lines">
            <thead>
                <tr>
                    @php
                        $itemHeaders = $rtlCells([
                            $ar(__('bid.pdf_item')),
                            $ar(__('bid.pdf_qty')),
                            $ar(__('bid.pdf_unit')),
                            $ar(__('bid.pdf_unit_price')),
                            $ar(__('bid.pdf_total')),
                        ]);
                    @endphp
                    @foreach($itemHeaders as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($bid->items as $item)
                    @php
                        $qty        = (float) ($item['qty'] ?? 0);
                        $unit_price = (float) ($item['unit_price'] ?? 0);
                        $lineCells  = $rtlCells([
                            ['cls' => '',    'val' => $ar($item['name'] ?? '')],
                            ['cls' => 'num', 'val' => number_format($qty)],
                            ['cls' => '',    'val' => $ar($item['unit'] ?? '')],
                            ['cls' => 'num', 'val' => $currency . ' ' . number_format($unit_price, 2)],
                            ['cls' => 'num', 'val' => $currency . ' ' . number_format($unit_price * max($qty, 1), 2)],
                        ]);
                    @endphp
                    <tr>
                        @foreach($lineCells as $cell)
                            <td class="{{ $cell['cls'] }}">{{ $cell['val'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($bid->notes)
    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_notes')) }}</div>
        <p>{{ $ar($bid->notes) }}</p>
    </div>
    @endif

    <div class="section">
        <div class="section-title">{{ $ar(__('bid.pdf_submitted_by')) }}</div>
        <p class="value">{{ $ar($bid->company?->name ?? '—') }}</p>
    </div>

    <div class="footer">
        {{ $ar(__('bid.pdf_footer', ['date' => now()->format('d / m / Y · H:i') . ' UTC'])) }}<br>
        TriLink Trading Platform
    </div>

</body>
</html>
