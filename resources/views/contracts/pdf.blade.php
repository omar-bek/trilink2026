@php
    /**
     * Bilingual UAE Sale & Supply Agreement (PDF render).
     *
     * Drafted to be enforceable under the principal UAE federal laws:
     *   • Federal Law No. 5 of 1985 — Civil Transactions Code
     *   • Federal Decree-Law No. 50 of 2022 — Commercial Transactions Law
     *   • Federal Decree-Law No. 8 of 2017 — Value Added Tax (VAT)
     *   • Federal Decree-Law No. 46 of 2021 — Electronic Transactions
     *   • Federal Decree-Law No. 45 of 2021 — Personal Data Protection (PDPL)
     *   • Federal Decree-Law No. 20 of 2018 — AML / CFT
     *   • Federal Decree-Law No. 51 of 2023 — Bankruptcy / Restructuring
     *
     * The clause set itself lives in ContractService::buildUaeContractTerms()
     * (translation-key driven). This template is responsible for the legal
     * chrome around it: parties block, recitals, articles, schedule and the
     * signature panel with the electronic-execution audit trail.
     *
     * Locale: the controller forces the locale via App::setLocale before
     * loading this view so the user gets the language they explicitly
     * requested (?lang=ar|en) regardless of their UI locale. Both languages
     * must always be available — per Federal Law 26/1981 the Arabic version
     * is the prevailing legal text before UAE courts.
     *
     * The `$pdfLocale` variable is preferred over app()->getLocale() so the
     * template stays correct even if a future caller doesn't restore the
     * original locale around its loadView() call.
     */
    use App\Support\ArabicShaper;

    $locale   = $pdfLocale ?? app()->getLocale();
    $isRtl    = $locale === 'ar';
    $dir      = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';
    // Body font: Arial. Bundled into storage/fonts/ via the dompdf font
    // installer (see config/dompdf.php → default_font = Arial). Arial covers
    // the Unicode Arabic Presentation Forms-B block (FE70–FEFF) at every
    // weight, which DejaVu Sans Bold did not — that's what was producing
    // the "?????" headings on the Arabic PDF. The Arabic letters themselves
    // are still pre-shaped through ArabicShaper before printing because
    // dompdf does no GSUB shaping of its own.
    $bodyFont = 'Arial, sans-serif';

    // ar() — convert any Arabic-bearing string into a pre-shaped, visually
    // ordered form for dompdf. Used wherever the template prints text that
    // may contain Arabic letters. No-op for pure Latin/numeric strings.
    // Suitable for SHORT, single-line content (titles, labels, badges).
    $ar = fn ($text) => ArabicShaper::shape((string) $text);

    // arLines() — for LONG, wrappable Arabic content. Rather than letting
    // dompdf wrap a fully-reversed string (which inverts the line order
    // because the LAST words land on the FIRST visual line), we break the
    // text into fixed-length chunks at word boundaries here in PHP, then
    // shape+reverse each chunk individually. Each chunk is a single visual
    // line that fits inside the column without dompdf needing to wrap.
    //
    // Default $maxChars is calibrated for the clause cell on A4 with
    // Arial 13.5px (the body size we use for Arabic clause text after
    // bumping it up for legibility):
    //   A4 width 595pt − @page margins 75pt ≈ 520pt usable (no separate
    //   num gutter anymore — the number is rendered inline at the
    //   trailing edge of the first line, so the full content width is
    //   available to the text)
    //   Arial 13.5px Arabic shaped width ≈ 4.45pt per char (measured
    //   empirically via FontMetrics::getTextWidth), so 520 / 4.45 ≈ 117
    //   chars fits the full width. We back off to 110 so that the
    //   trailing inline `<span class="clause-num">1.</span>` on the first
    //   line, plus any justify padding, never push the last word off the
    //   right edge.
    //
    // Returns array<int, string> of pre-shaped lines for RTL, or the
    // original text in a single-element array for LTR (where dompdf
    // handles wrapping itself).
    $arLines = function ($text, int $maxChars = 110) use ($ar, $isRtl) {
        $text = (string) $text;
        if (!$isRtl) {
            return [$text];
        }
        $lines = ArabicShaper::breakIntoLines($text, $maxChars);
        return array_map($ar, $lines);
    };

    // rtlCells() — for RTL contracts, reverse the LTR memory order of a
    // table row's cells so the FIRST logical column ends up RIGHTMOST on
    // the page (which is where an Arabic reader's eye starts). Dompdf
    // does not honour `direction: rtl` for table column ordering, so we
    // do it manually at the markup level. For LTR contracts the array
    // passes through unchanged.
    $rtlCells = fn (array $cells) => $isRtl ? array_reverse($cells) : $cells;

    // Letter-spacing must be 0 for Arabic — any horizontal gap inserted
    // between glyphs by the renderer destroys the visual continuity of
    // shaped cursive text and makes the labels look like a sequence of
    // disconnected isolated letters even when shaping is correct. Same
    // applies to text-transform: uppercase, which is a no-op for Arabic
    // (no case) but can confuse dompdf's font selection in some cases.
    $tracking = fn ($em) => $isRtl ? '0' : $em;
    $caps     = $isRtl ? 'none' : 'uppercase';

    // Sections come from the controller pre-rendered in the requested
    // locale (handles bilingual contracts and legacy single-locale
    // regeneration). Fall back to the contract's stored terms for callers
    // that haven't been migrated to pass `$pdfSections`.
    $sections = $pdfSections ?? null;
    if ($sections === null && !empty($contract->terms)) {
        $decoded = is_string($contract->terms) ? json_decode($contract->terms, true) : $contract->terms;
        if (is_array($decoded)) {
            // Bilingual envelope detection
            if (isset($decoded['en']) || isset($decoded['ar'])) {
                $sections = $decoded[$locale] ?? $decoded['en'] ?? $decoded['ar'] ?? [];
            } else {
                $sections = $decoded;
            }
        }
    }
    $sections = $sections ?? [];

    // Pull amounts breakdown (subtotal / tax / total). The amounts JSON is
    // populated by ContractService and may be missing on legacy contracts,
    // so every read is null-safe.
    $amounts   = $contract->amounts ?? [];
    $subtotal  = $amounts['subtotal']  ?? null;
    $taxRate   = $amounts['tax_rate']  ?? null;
    $taxAmount = $amounts['tax']       ?? null;
    $total     = $amounts['total']     ?? $contract->total_amount;
    $currency  = $contract->currency ?? 'AED';

    $signatures      = $contract->signatures ?? [];
    $paymentSchedule = $contract->payment_schedule ?? [];

    // Resolve party-level signature info so each signature block can
    // render its electronic execution audit trail.
    $signatureFor = function ($companyId) use ($signatures) {
        foreach ($signatures as $sig) {
            $sig = is_array($sig) ? $sig : (is_string($sig) ? (json_decode($sig, true) ?: []) : []);
            if (($sig['company_id'] ?? null) == $companyId) {
                return $sig;
            }
        }
        return null;
    };

    $renderParty = function ($company, $fallbackName) use ($isRtl) {
        if (!$company) {
            return ['name' => $fallbackName ?? '—', 'reg' => '—', 'tax' => '—', 'addr' => '—', 'country' => '—', 'email' => '—', 'phone' => '—'];
        }
        return [
            'name'    => $isRtl ? ($company->name_ar ?: $company->name) : $company->name,
            'reg'     => $company->registration_number ?: '—',
            'tax'     => $company->tax_number ?: '—',
            'addr'    => $company->address ?: '—',
            'country' => $company->country ?: '—',
            'email'   => $company->email ?: '—',
            'phone'   => $company->phone ?: '—',
        ];
    };

    $buyerNameFromParties = collect($contract->parties ?? [])
        ->firstWhere('role', 'buyer')['name'] ?? null;
    $supplierNameFromParties = collect($contract->parties ?? [])
        ->firstWhere('role', 'supplier')['name'] ?? null;

    $buyer    = $renderParty($buyerCompany ?? $contract->buyerCompany, $buyerNameFromParties);
    $supplier = $renderParty($supplierCompany ?? null, $supplierNameFromParties);

    $buyerSig    = $signatureFor($buyerCompany?->id ?? $contract->buyer_company_id ?? null);
    $supplierSig = $signatureFor($supplierCompany?->id ?? null);

    // Resolve signature + stamp asset paths from the public disk so the
    // PDF render can embed the images directly. Dompdf needs an absolute
    // filesystem path (not a URL) to load the asset, so we resolve via
    // public_path() rather than Storage::url(). Returns null when the
    // asset is missing — the renderer falls back to a blank line so the
    // PDF still draws cleanly even for legacy companies that pre-date
    // the signature/stamp upload feature.
    $assetPath = function (?string $relativePath): ?string {
        if (empty($relativePath)) {
            return null;
        }
        $candidate = public_path('storage/' . ltrim($relativePath, '/'));
        return is_file($candidate) ? $candidate : null;
    };

    $buyerCo    = $buyerCompany ?? $contract->buyerCompany;
    $supplierCo = $supplierCompany ?? null;

    $buyerSignaturePath = $buyerSig    ? $assetPath($buyerCo?->signature_path)    : null;
    $buyerStampPath     = $buyerSig    ? $assetPath($buyerCo?->stamp_path)        : null;
    $supplierSignaturePath = $supplierSig ? $assetPath($supplierCo?->signature_path) : null;
    $supplierStampPath     = $supplierSig ? $assetPath($supplierCo?->stamp_path)     : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ __('contracts.pdf_title') }} — {{ $contract->contract_number }}</title>
    <style>
        @page { margin: 60px 50px 70px 50px; }
        body {
            font-family: {{ $bodyFont }};
            font-size: 11px;
            line-height: {{ $isRtl ? '1.85' : '1.65' }};
            color: #1a1d29;
            direction: {{ $dir }};
            unicode-bidi: embed;
        }
        /* Force every block-level container to inherit the document direction
           — dompdf otherwise lets nested tables/divs default back to LTR
           which throws Arabic numerals and table cells out of alignment. */
        div, table, td, th, p, ol, ul, li, h1, h2, h3, h4, span {
            direction: {{ $dir }};
            unicode-bidi: embed;
        }
        /* Numbers, dates and currency codes stay LTR even inside RTL paragraphs. */
        .ltr-inline { direction: ltr; unicode-bidi: bidi-override; display: inline-block; }

        /* ---------- Header / footer ---------- */
        .doc-header {
            border-bottom: 2px solid #0f1117;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .doc-header .brand {
            text-align: center;
            font-size: 10px;
            letter-spacing: {{ $tracking('0.18em') }};
            text-transform: {{ $caps }};
            color: #4f7cff;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .doc-header h1 {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #0f1117;
            margin: 0 0 4px 0;
            letter-spacing: -0.4px;
        }
        .doc-header .subtitle {
            text-align: center;
            font-size: 10.5px;
            color: #4f5366;
            margin-bottom: 10px;
        }
        /* Language ribbon — makes it crystal clear which language version
           the reader is holding so the bilingual pair can't be confused. */
        .lang-ribbon {
            text-align: center;
            font-size: {{ $isRtl ? '11px' : '9px' }};
            font-weight: 700;
            letter-spacing: {{ $tracking('0.14em') }};
            text-transform: {{ $caps }};
            color: #4f7cff;
            background: rgba(79,124,255,0.08);
            border: 1px solid rgba(79,124,255,0.25);
            border-radius: 999px;
            display: inline-block;
            padding: 3px 12px;
            margin-top: 6px;
        }
        .doc-meta {
            font-size: 10px;
            color: #4f5366;
            margin-top: 12px;
        }
        .doc-meta table { width: 100%; }
        .doc-meta td { padding: 1px 0; text-align: {{ $textAlign }}; }
        .doc-meta .label { color: #8b8f9c; text-transform: {{ $caps }}; letter-spacing: {{ $tracking('0.08em') }}; font-size: 9px; }
        .doc-meta .value { color: #0f1117; font-weight: 600; }

        /* ---------- Recitals / NOW THEREFORE block ---------- */
        .between {
            text-align: center;
            font-weight: 700;
            font-size: {{ $isRtl ? '13px' : '11px' }};
            letter-spacing: {{ $tracking('0.22em') }};
            color: #0f1117;
            margin: 18px 0 10px 0;
            text-transform: {{ $caps }};
        }

        /* ---------- Party panels ---------- */
        .party {
            border: 1px solid #d8dce6;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 10px;
            background: #fafbff;
        }
        .party .role {
            display: inline-block;
            font-size: {{ $isRtl ? '11px' : '9px' }};
            letter-spacing: {{ $tracking('0.14em') }};
            text-transform: {{ $caps }};
            color: #4f7cff;
            background: rgba(79,124,255,0.08);
            border: 1px solid rgba(79,124,255,0.25);
            border-radius: 999px;
            padding: 2px 8px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .party .name {
            font-size: 14px;
            font-weight: 700;
            color: #0f1117;
            margin-bottom: 4px;
        }
        .party table { width: 100%; font-size: 10px; }
        .party td { padding: 2px 0; vertical-align: top; text-align: {{ $textAlign }}; }
        .party td.k { color: #6b7080; width: 22%; }
        .party td.v { color: #0f1117; font-weight: 600; width: 28%; }

        .recitals {
            margin: 18px 0 14px 0;
            padding: 14px 16px;
            background: #f6f8ff;
            border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #4f7cff;
            border-radius: 4px;
        }
        .recitals .head {
            font-size: {{ $isRtl ? '12px' : '10px' }};
            font-weight: 700;
            text-transform: {{ $caps }};
            letter-spacing: {{ $tracking('0.16em') }};
            color: #4f7cff;
            margin-bottom: 8px;
        }
        .recitals p { margin: 0 0 6px 0; font-size: 10.5px; color: #2d3548; }
        .now-therefore {
            font-weight: 700;
            font-size: 11px;
            color: #0f1117;
            margin-top: 6px;
        }

        /* ---------- Articles ---------- */
        .article {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .article-head {
            font-size: 12px;
            font-weight: 700;
            color: #0f1117;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e8f0;
            padding-bottom: 4px;
        }
        .article-head .num {
            display: inline-block;
            background: #0f1117;
            color: #fff;
            font-size: 9.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            {{ $isRtl ? 'margin-left' : 'margin-right' }}: 8px;
            letter-spacing: {{ $tracking('0.04em') }};
        }
        /* Manually-rendered clause items. The number is rendered INLINE
           at the trailing edge of the first line's text (which is the
           rightmost position visually in RTL), so it sits directly
           adjacent to the start of the first word with no gutter gap.
           Continuation lines then naturally fill the same column width
           starting at the same right edge as the first line.
           - margin: 0 — nothing pushes the clause inward, the right
             edge of the text is flush against the page content area
             right edge (the @page margin)
           - text-align: justify — words distribute spacing so each line
             (except the last) hits both left and right edges */
        .article-clause {
            font-size: {{ $isRtl ? '13.5px' : '10.5px' }};
            color: #2d3548;
            line-height: 1.95;
            margin: 0 0 10px 0;
            padding: 0;
            text-align: justify;
            direction: {{ $dir }};
        }
        .article-clause .clause-num {
            font-weight: 700;
            color: #0f1117;
            font-size: {{ $isRtl ? '14px' : '11px' }};
            {{ $isRtl ? 'margin-left' : 'margin-right' }}: 2px;
            direction: ltr;
            unicode-bidi: embed;
        }

        /* ---------- Schedule (Schedule A) ---------- */
        .schedule {
            margin: 18px 0;
            page-break-inside: avoid;
        }
        .schedule .schedule-head {
            font-size: 12px;
            font-weight: 700;
            color: #0f1117;
            margin-bottom: 8px;
            border-bottom: 1px solid #0f1117;
            padding-bottom: 4px;
        }
        .schedule table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .schedule th {
            background: #0f1117;
            color: #fff;
            font-size: {{ $isRtl ? '11px' : '9px' }};
            font-weight: 700;
            letter-spacing: {{ $tracking('0.04em') }};
            text-transform: {{ $caps }};
            padding: 7px 8px;
            text-align: {{ $textAlign }};
            border: 1px solid #0f1117;
        }
        .schedule td {
            border: 1px solid #d8dce6;
            padding: 7px 8px;
            color: #2d3548;
            text-align: {{ $textAlign }};
        }
        .schedule tr.totals td {
            background: #f6f8ff;
            font-weight: 700;
            color: #0f1117;
        }

        /* ---------- Signature blocks ---------- */
        .sign-witness {
            margin-top: 22px;
            padding: 12px 14px;
            background: #f6f8ff;
            border-radius: 4px;
            font-size: 10.5px;
            color: #2d3548;
            text-align: justify;
        }
        .sign-grid {
            margin-top: 18px;
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
        }
        .sign-cell {
            border: 1px solid #d8dce6;
            border-radius: 6px;
            padding: 14px;
            background: #fff;
            width: 50%;
            vertical-align: top;
        }
        .sign-cell .role {
            font-size: {{ $isRtl ? '11px' : '9px' }};
            letter-spacing: {{ $tracking('0.14em') }};
            text-transform: {{ $caps }};
            color: #4f7cff;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .sign-cell .name {
            font-size: 12px;
            font-weight: 700;
            color: #0f1117;
            margin-bottom: 12px;
        }
        .sign-cell .field {
            font-size: 9.5px;
            color: #6b7080;
            margin: 8px 0 2px 0;
            font-weight: 700;
        }
        .sign-cell .line {
            border-bottom: 1px solid #0f1117;
            min-height: 16px;
            margin-bottom: 6px;
        }
        /* Two-column row inside the sign cell — used to put the signature
           line next to the company-stamp box. dompdf does not support
           flexbox, so we fall back to a tiny inner table. */
        .sign-cell .sign-row {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-top: 6px;
        }
        .sign-cell .sign-row td {
            vertical-align: top;
            padding: 0;
        }
        .sign-cell .sig-col { width: 55%; }
        .sign-cell .stamp-col { width: 45%; }
        /* Stamp box — clearly bordered area dedicated to the company seal.
           Dashed border so it's obvious this is a placeholder waiting for
           a wet stamp / e-stamp image. */
        .sign-cell .stamp-box {
            border: 1.5px dashed #4f7cff;
            border-radius: 6px;
            background: #f6f8ff;
            min-height: 78px;
            padding: 6px 8px;
            text-align: center;
            color: #4f7cff;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: {{ $tracking('0.06em') }};
        }
        .sign-cell .stamp-box .stamp-hint {
            display: block;
            margin-top: 32px;
            font-size: 8px;
            font-weight: 400;
            color: #8b8f9c;
            font-style: italic;
        }
        /* Embedded signature image — drawn over the signature line so the
           authorised signatory's actual scan/written signature appears in
           the cell. Width is constrained to keep the layout stable across
           contracts whose signature image aspect ratios differ wildly. */
        .sign-cell .signature-img-wrap {
            min-height: 60px;
            border-bottom: 1px solid #0f1117;
            padding: 4px 0;
            text-align: center;
            margin-bottom: 6px;
        }
        .sign-cell .signature-img {
            max-height: 50px;
            max-width: 100%;
        }
        /* Embedded company stamp — same constraints as the signature
           image. The dashed placeholder is replaced entirely so the seal
           sits in a clean bordered area. */
        .sign-cell .stamp-img-wrap {
            border: 1px solid #4f7cff;
            border-radius: 6px;
            background: #fff;
            min-height: 78px;
            padding: 4px 6px;
            text-align: center;
        }
        .sign-cell .stamp-img {
            max-height: 70px;
            max-width: 100%;
        }
        .sign-cell .audit {
            margin-top: 12px;
            padding: 10px 12px;
            background: #f0fdf9;
            border: 1px solid #00d9b5;
            border-{{ $isRtl ? 'right' : 'left' }}: 4px solid #00d9b5;
            border-radius: 4px;
            font-size: 9.5px;
            color: #1a3d36;
            line-height: 1.7;
        }
        .sign-cell .audit.unsigned {
            background: #fff8eb;
            border-color: #ffb020;
            border-{{ $isRtl ? 'right' : 'left' }}-color: #ffb020;
            color: #6b5320;
        }
        .sign-cell .audit .label {
            display: block;
            color: #00b894;
            font-weight: 700;
            font-size: 9px;
            margin-bottom: 4px;
            padding-bottom: 4px;
            border-bottom: 1px dotted #00d9b5;
        }
        .sign-cell .audit.unsigned .label {
            color: #b87a0e;
            border-bottom-color: #ffb020;
        }
        .sign-cell .audit .row {
            display: block;
            margin-top: 2px;
            color: #2d3548;
        }
        .sign-cell .audit .row .key {
            font-weight: 700;
            color: #4f5366;
            {{ $isRtl ? 'margin-left' : 'margin-right' }}: 4px;
        }

        /* ---------- Footer disclaimer ---------- */
        .disclaimer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #d8dce6;
            font-size: 8.5px;
            color: #8b8f9c;
            text-align: center;
            line-height: 1.6;
        }
        .footer-meta {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #8b8f9c;
        }
    </style>
</head>
<body>

    {{-- ===================== HEADER ===================== --}}
    <div class="doc-header">
        <div class="brand">TriLink Trading Platform</div>
        <h1>{{ $ar(__('contracts.pdf_title')) }}</h1>
        <div class="subtitle">{{ $ar(__('contracts.pdf_subtitle')) }}</div>
        <div style="text-align: center;">
            <span class="lang-ribbon">
                {{ $ar($isRtl ? 'النسخة العربية' : 'English Version') }}
                ·
                @if($isRtl)
                    {{ $ar('وتسري قانونًا أمام محاكم الإمارات') }}
                @else
                    Reference translation — Arabic prevails
                @endif
            </span>
        </div>

        <div class="doc-meta">
            <table>
                <tr>
                    <td class="label">{{ $ar(__('contracts.pdf_dated')) }}</td>
                    <td class="value"><span class="ltr-inline">{{ ($contract->start_date ?? $contract->created_at)->format('d / m / Y') }}</span></td>
                    <td class="label">{{ $ar(__('contracts.pdf_article')) }} №</td>
                    <td class="value"><span class="ltr-inline">{{ $contract->contract_number }}</span></td>
                </tr>
                <tr>
                    <td class="label">{{ $ar(__('contracts.title')) }}</td>
                    <td class="value" colspan="3">{{ $ar($contract->title) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ===================== PARTIES ===================== --}}
    <div class="between">{{ $ar(__('contracts.pdf_between')) }}</div>

    @php
        // Build the (label, value) pairs that go into each party panel.
        // For RTL we reverse each row so the labels (the "fixed" columns)
        // end up on the right side of the page.
        $partyRows = function (array $p) use ($ar, $rtlCells) {
            return [
                $rtlCells([
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_reg_number'))],
                    ['cls' => 'v', 'val' => $ar($p['reg'])],
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_tax_number'))],
                    ['cls' => 'v', 'val' => $ar($p['tax'])],
                ]),
                $rtlCells([
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_address'))],
                    ['cls' => 'v', 'val' => $ar($p['addr'])],
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_country'))],
                    ['cls' => 'v', 'val' => $ar($p['country'])],
                ]),
                $rtlCells([
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_email'))],
                    ['cls' => 'v', 'val' => $ar($p['email'])],
                    ['cls' => 'k', 'val' => $ar(__('contracts.pdf_phone'))],
                    ['cls' => 'v', 'val' => $ar($p['phone'])],
                ]),
            ];
        };
    @endphp

    <div class="party">
        <span class="role">{{ $ar(__('contracts.pdf_party_buyer')) }}</span>
        <div class="name">{{ $ar($buyer['name']) }}</div>
        <table>
            @foreach($partyRows($buyer) as $row)
                <tr>
                    @foreach($row as $cell)
                        <td class="{{ $cell['cls'] }}">{{ $cell['val'] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>

    <div class="between">{{ $ar(__('contracts.pdf_and')) }}</div>

    <div class="party">
        <span class="role">{{ $ar(__('contracts.pdf_party_supplier')) }}</span>
        <div class="name">{{ $ar($supplier['name']) }}</div>
        <table>
            @foreach($partyRows($supplier) as $row)
                <tr>
                    @foreach($row as $cell)
                        <td class="{{ $cell['cls'] }}">{{ $cell['val'] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>

    {{-- ===================== RECITALS ===================== --}}
    <div class="recitals">
        <div class="head">{{ $ar(__('contracts.pdf_recitals')) }}</div>
        @foreach(['contracts.pdf_whereas_1', 'contracts.pdf_whereas_2', 'contracts.pdf_whereas_3'] as $key)
            @foreach($arLines(__($key), 130) as $line)
                <p>{{ $line }}</p>
            @endforeach
        @endforeach
        @foreach($arLines(__('contracts.pdf_now_therefore'), 90) as $line)
            <p class="now-therefore">{{ $line }}</p>
        @endforeach
    </div>

    {{-- ===================== ARTICLES (clauses) ===================== --}}
    @foreach($sections as $i => $section)
        <div class="article">
            <div class="article-head">
                <span class="num">{{ $ar(__('contracts.pdf_article')) }} {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                {{ $ar($section['title'] ?? '') }}
            </div>
            @if(!empty($section['items']) && is_array($section['items']))
                {{-- One <div> per clause. dompdf renders inline content
                     in document order LTR (it does NOT honour RTL for
                     inline element placement), so we put the number
                     <span> AFTER the first line's text in source order —
                     which lands it at the rightmost visual position,
                     directly adjacent to the first Arabic word with no
                     gutter gap. Subsequent lines have no number and
                     start at the cell's right edge as usual. --}}
                @foreach($section['items'] as $j => $item)
                    @php
                        $lines = $arLines($item);
                        $firstLine = e($lines[0] ?? '');
                        $rest      = array_slice($lines, 1);
                    @endphp
                    <div class="article-clause">
                        {!! $firstLine !!}<span class="clause-num">{{ $j + 1 }}.</span>
                        @foreach($rest as $line)
                            <br>{{ $line }}
                        @endforeach
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    {{-- ===================== SCHEDULE A — milestones ===================== --}}
    @if(!empty($paymentSchedule))
    <div class="schedule">
        <div class="schedule-head">{{ $ar(__('contracts.pdf_payment_schedule_title')) }}</div>
        <table>
            <thead>
                <tr>
                    @php
                        // Header cell order. Logical (LTR) order:
                        //   #, Milestone, Percentage, Amount, Due Date
                        // For Arabic the cell order is reversed in MEMORY so
                        // dompdf places # at the rightmost visual column —
                        // which is where Arabic readers expect the "row
                        // identifier" anchor column.
                        $headerCells = $rtlCells([
                            '#',
                            $ar(__('contracts.pdf_milestone')),
                            $ar(__('contracts.pdf_pct')),
                            $ar(__('contracts.pdf_amount')),
                            $ar(__('contracts.pdf_due_date')),
                        ]);
                    @endphp
                    @foreach($headerCells as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($paymentSchedule as $i => $m)
                    @php
                        $msKey   = $m['milestone'] ?? ('milestone_' . ($i + 1));
                        $msLabel = match ($msKey) {
                            'advance'    => __('contracts.advance_payment'),
                            'production' => __('contracts.production_completion'),
                            'delivery'   => __('contracts.delivery_payment'),
                            'final'      => __('contracts.received'),
                            default      => $msKey,
                        };
                        $rowCells = $rtlCells([
                            $i + 1,
                            $ar($msLabel),
                            ($m['percentage'] ?? '—') . '%',
                            ($m['currency'] ?? $currency) . ' ' . number_format((float)($m['amount'] ?? 0), 2),
                            $m['due_date'] ?? '—',
                        ]);
                    @endphp
                    <tr>
                        @foreach($rowCells as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if($subtotal !== null || $taxAmount !== null)
                <tr class="totals">
                    @php
                        // Totals row: same column order as the body so the
                        // amount column lines up with the "Amount" header.
                        $totalLabel = $ar(__('contracts.total_value'));
                        $totalAmountCell = $currency . ' ' . number_format((float) $total, 2)
                            . ($taxRate ? ' (' . $ar(__('contracts.tax_vat')) . ' ' . rtrim(rtrim(number_format((float) $taxRate, 2), '0'), '.') . '%)' : '');
                    @endphp
                    @if($isRtl)
                        {{-- RTL columns (memory LTR): Due Date | Amount | % | Milestone | # --}}
                        <td colspan="2">{{ $totalAmountCell }}</td>
                        <td colspan="3" style="text-align: left;">{{ $totalLabel }}</td>
                    @else
                        <td colspan="3" style="text-align: right;">{{ $totalLabel }}</td>
                        <td colspan="2">{{ $totalAmountCell }}</td>
                    @endif
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif

    {{-- ===================== IN WITNESS WHEREOF ===================== --}}
    <div class="sign-witness">
        @foreach($arLines(__('contracts.pdf_in_witness'), 130) as $line)
            <div>{{ $line }}</div>
        @endforeach
    </div>

    @php
        // Render a single party's signature cell. Wrapped in a closure so
        // the buyer + supplier blocks stay byte-identical and any future
        // tweak to the layout only happens in one place.
        $renderSignCell = function (string $roleLabel, array $party, ?array $sig, ?string $sigImg, ?string $stampImg) use ($ar, $isRtl) {
            return [
                'role'      => $ar($roleLabel),
                'name'      => $ar($party['name']),
                'sig'       => $sig,
                'party'     => $party,
                'sig_img'   => $sigImg,
                'stamp_img' => $stampImg,
            ];
        };
        $cells = [
            $renderSignCell(__('contracts.pdf_party_buyer'),    $buyer,    $buyerSig,    $buyerSignaturePath,    $buyerStampPath),
            $renderSignCell(__('contracts.pdf_party_supplier'), $supplier, $supplierSig, $supplierSignaturePath, $supplierStampPath),
        ];
    @endphp

    <table class="sign-grid">
        <tr>
            @foreach($cells as $c)
                <td class="sign-cell">
                    <div class="role">{{ $c['role'] }}</div>
                    <div class="name">{{ $c['name'] }}</div>

                    <div class="field">{{ $ar(__('contracts.pdf_full_name')) }}</div>
                    <div class="line"></div>

                    <div class="field">{{ $ar(__('contracts.pdf_capacity')) }}</div>
                    <div class="line"></div>

                    {{-- Signature line + dedicated stamp box, side by side.
                         When the party has signed AND the company has uploaded
                         a signature/stamp image we embed the actual image
                         (loaded via absolute filesystem path so dompdf can
                         read it). Otherwise we render the empty placeholder
                         line / dashed box exactly like the legacy template. --}}
                    <table class="sign-row">
                        <tr>
                            <td class="sig-col">
                                <div class="field">{{ $ar(__('contracts.pdf_signature')) }}</div>
                                @if($c['sig_img'])
                                    <div class="signature-img-wrap">
                                        <img src="{{ $c['sig_img'] }}" class="signature-img" alt="signature">
                                    </div>
                                @else
                                    <div class="line" style="min-height: 50px;"></div>
                                @endif
                            </td>
                            <td class="stamp-col">
                                <div class="field">{{ $ar(__('contracts.pdf_company_stamp')) }}</div>
                                @if($c['stamp_img'])
                                    <div class="stamp-img-wrap">
                                        <img src="{{ $c['stamp_img'] }}" class="stamp-img" alt="stamp">
                                    </div>
                                @else
                                    <div class="stamp-box">
                                        <span class="stamp-hint">{{ $ar(__('contracts.pdf_stamp_hint')) }}</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <div class="field">{{ $ar(__('contracts.pdf_date_label')) }}</div>
                    <div class="line">
                        @if($c['sig'] && !empty($c['sig']['signed_at']))
                            <span class="ltr-inline" style="font-size:10px;font-weight:700;color:#0f1117;">
                                {{ \Carbon\Carbon::parse($c['sig']['signed_at'])->toDayDateTimeString() }} UTC
                            </span>
                        @endif
                    </div>

                    @if($c['sig'])
                        <div class="audit">
                            <span class="label">{{ $ar(__('contracts.pdf_audit_trail')) }}</span>
                            <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_signed_by')) }}:</span>user #{{ $c['sig']['user_id'] ?? '—' }}</span>
                            <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_acting_for')) }}:</span>{{ $c['name'] }}</span>
                            <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_at')) }}:</span><span class="ltr-inline">{{ $c['sig']['signed_at'] ?? '—' }}</span></span>
                            @if(!empty($c['sig']['ip_address']))
                                <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_ip')) }}:</span><span class="ltr-inline">{{ $c['sig']['ip_address'] }}</span></span>
                            @endif
                            @if(!empty($c['sig']['user_agent']))
                                <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_device')) }}:</span><span class="ltr-inline" style="font-size:8.5px;">{{ \Illuminate\Support\Str::limit($c['sig']['user_agent'], 80) }}</span></span>
                            @endif
                            @if(!empty($c['sig']['contract_hash']))
                                <span class="row"><span class="key">{{ $ar(__('contracts.pdf_audit_hash')) }}:</span><span class="ltr-inline" style="font-size:8px;font-family:monospace;">{{ \Illuminate\Support\Str::limit($c['sig']['contract_hash'], 32) }}</span></span>
                            @endif
                        </div>
                    @else
                        <div class="audit unsigned">
                            <span class="label">{{ $ar(__('contracts.pdf_audit_trail')) }}</span>
                            <span class="row">{{ $ar(__('contracts.pdf_audit_unsigned')) }}</span>
                        </div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    {{-- ===================== DISCLAIMER ===================== --}}
    <div class="disclaimer">
        {{ $ar(__('contracts.pdf_disclaimer')) }}<br>
        v{{ $contract->version }} · {{ $contract->contract_number }} · {{ now()->format('Y-m-d H:i') }} UTC
    </div>

</body>
</html>
