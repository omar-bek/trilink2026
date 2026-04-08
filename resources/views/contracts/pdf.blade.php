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
     * Locale: respects app()->getLocale(). When 'ar' the page renders RTL
     * with Arabic typography; otherwise LTR English. Per Federal Law 26/1981
     * the Arabic version prevails before UAE courts (see language clause).
     */
    $isRtl    = app()->getLocale() === 'ar';
    $dir      = $isRtl ? 'rtl' : 'ltr';
    $textAlign = $isRtl ? 'right' : 'left';

    $sections = [];
    if (!empty($contract->terms)) {
        $decoded = json_decode($contract->terms, true);
        if (is_array($decoded)) {
            $sections = $decoded;
        }
    }

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
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('contracts.pdf_title') }} — {{ $contract->contract_number }}</title>
    <style>
        @page { margin: 60px 50px 70px 50px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.65;
            color: #1a1d29;
            direction: {{ $dir }};
        }

        /* ---------- Header / footer ---------- */
        .doc-header {
            border-bottom: 2px solid #0f1117;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .doc-header .brand {
            text-align: center;
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #4f7cff;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .doc-header h1 {
            text-align: center;
            font-size: 22px;
            font-weight: 800;
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
        .doc-meta {
            font-size: 10px;
            color: #4f5366;
            margin-top: 8px;
        }
        .doc-meta table { width: 100%; }
        .doc-meta td { padding: 1px 0; }
        .doc-meta .label { color: #8b8f9c; text-transform: uppercase; letter-spacing: 0.08em; font-size: 9px; }
        .doc-meta .value { color: #0f1117; font-weight: 600; }

        /* ---------- Recitals / NOW THEREFORE block ---------- */
        .between {
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.22em;
            color: #0f1117;
            margin: 18px 0 10px 0;
            text-transform: uppercase;
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
            font-size: 9px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
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
            font-weight: 800;
            color: #0f1117;
            margin-bottom: 4px;
        }
        .party table { width: 100%; font-size: 10px; }
        .party td { padding: 2px 0; vertical-align: top; }
        .party td.k { color: #6b7080; width: 38%; }
        .party td.v { color: #0f1117; font-weight: 600; }

        .recitals {
            margin: 18px 0 14px 0;
            padding: 14px 16px;
            background: #f6f8ff;
            border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #4f7cff;
            border-radius: 4px;
        }
        .recitals .head {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.16em;
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
            font-weight: 800;
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
            letter-spacing: 0.04em;
        }
        .article ol {
            padding-{{ $isRtl ? 'right' : 'left' }}: 18px;
            margin: 4px 0 0 0;
        }
        .article li { margin-bottom: 4px; font-size: 10.5px; color: #2d3548; line-height: 1.7; text-align: justify; }

        /* ---------- Schedule (Schedule A) ---------- */
        .schedule {
            margin: 18px 0;
            page-break-inside: avoid;
        }
        .schedule .schedule-head {
            font-size: 12px;
            font-weight: 800;
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
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
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
            font-size: 9px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #4f7cff;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .sign-cell .name {
            font-size: 12px;
            font-weight: 800;
            color: #0f1117;
            margin-bottom: 12px;
        }
        .sign-cell .field {
            font-size: 9.5px;
            color: #6b7080;
            margin: 6px 0 1px 0;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .sign-cell .line {
            border-bottom: 1px solid #0f1117;
            min-height: 14px;
            margin-bottom: 6px;
        }
        .sign-cell .audit {
            margin-top: 10px;
            padding: 8px 10px;
            background: #f6f8ff;
            border-{{ $isRtl ? 'right' : 'left' }}: 2px solid #00d9b5;
            font-size: 9px;
            color: #2d3548;
        }
        .sign-cell .audit.unsigned {
            border-{{ $isRtl ? 'right' : 'left' }}-color: #ffb020;
            color: #6b7080;
            font-style: italic;
        }
        .sign-cell .audit .label {
            color: #00d9b5;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 8.5px;
        }
        .sign-cell .audit.unsigned .label { color: #ffb020; }

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
        <h1>{{ __('contracts.pdf_title') }}</h1>
        <div class="subtitle">{{ __('contracts.pdf_subtitle') }}</div>

        <div class="doc-meta">
            <table>
                <tr>
                    <td class="label">{{ __('contracts.pdf_dated') }}</td>
                    <td class="value">{{ ($contract->start_date ?? $contract->created_at)->format('d / m / Y') }}</td>
                    <td class="label">{{ __('contracts.pdf_article') }} №</td>
                    <td class="value">{{ $contract->contract_number }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('contracts.title') }}</td>
                    <td class="value" colspan="3">{{ $contract->title }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ===================== PARTIES ===================== --}}
    <div class="between">{{ __('contracts.pdf_between') }}</div>

    <div class="party">
        <span class="role">{{ __('contracts.pdf_party_buyer') }}</span>
        <div class="name">{{ $buyer['name'] }}</div>
        <table>
            <tr>
                <td class="k">{{ __('contracts.pdf_reg_number') }}</td>
                <td class="v">{{ $buyer['reg'] }}</td>
                <td class="k">{{ __('contracts.pdf_tax_number') }}</td>
                <td class="v">{{ $buyer['tax'] }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('contracts.pdf_address') }}</td>
                <td class="v">{{ $buyer['addr'] }}</td>
                <td class="k">{{ __('contracts.pdf_country') }}</td>
                <td class="v">{{ $buyer['country'] }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('contracts.pdf_email') }}</td>
                <td class="v">{{ $buyer['email'] }}</td>
                <td class="k">{{ __('contracts.pdf_phone') }}</td>
                <td class="v">{{ $buyer['phone'] }}</td>
            </tr>
        </table>
    </div>

    <div class="between">{{ __('contracts.pdf_and') }}</div>

    <div class="party">
        <span class="role">{{ __('contracts.pdf_party_supplier') }}</span>
        <div class="name">{{ $supplier['name'] }}</div>
        <table>
            <tr>
                <td class="k">{{ __('contracts.pdf_reg_number') }}</td>
                <td class="v">{{ $supplier['reg'] }}</td>
                <td class="k">{{ __('contracts.pdf_tax_number') }}</td>
                <td class="v">{{ $supplier['tax'] }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('contracts.pdf_address') }}</td>
                <td class="v">{{ $supplier['addr'] }}</td>
                <td class="k">{{ __('contracts.pdf_country') }}</td>
                <td class="v">{{ $supplier['country'] }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('contracts.pdf_email') }}</td>
                <td class="v">{{ $supplier['email'] }}</td>
                <td class="k">{{ __('contracts.pdf_phone') }}</td>
                <td class="v">{{ $supplier['phone'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ===================== RECITALS ===================== --}}
    <div class="recitals">
        <div class="head">{{ __('contracts.pdf_recitals') }}</div>
        <p>{{ __('contracts.pdf_whereas_1') }}</p>
        <p>{{ __('contracts.pdf_whereas_2') }}</p>
        <p>{{ __('contracts.pdf_whereas_3') }}</p>
        <p class="now-therefore">{{ __('contracts.pdf_now_therefore') }}</p>
    </div>

    {{-- ===================== ARTICLES (clauses) ===================== --}}
    @foreach($sections as $i => $section)
        <div class="article">
            <div class="article-head">
                <span class="num">{{ __('contracts.pdf_article') }} {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                {{ $section['title'] ?? '' }}
            </div>
            @if(!empty($section['items']) && is_array($section['items']))
                <ol>
                    @foreach($section['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endforeach

    {{-- ===================== SCHEDULE A — milestones ===================== --}}
    @if(!empty($paymentSchedule))
    <div class="schedule">
        <div class="schedule-head">{{ __('contracts.pdf_payment_schedule_title') }}</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('contracts.pdf_milestone') }}</th>
                    <th>{{ __('contracts.pdf_pct') }}</th>
                    <th>{{ __('contracts.pdf_amount') }}</th>
                    <th>{{ __('contracts.pdf_due_date') }}</th>
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
                    @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $msLabel }}</td>
                        <td>{{ $m['percentage'] ?? '—' }}%</td>
                        <td>{{ ($m['currency'] ?? $currency) }} {{ number_format((float)($m['amount'] ?? 0), 2) }}</td>
                        <td>{{ $m['due_date'] ?? '—' }}</td>
                    </tr>
                @endforeach
                @if($subtotal !== null || $taxAmount !== null)
                <tr class="totals">
                    <td colspan="3" style="text-align: {{ $isRtl ? 'left' : 'right' }};">{{ __('contracts.total_value') }}</td>
                    <td colspan="2">{{ $currency }} {{ number_format((float) $total, 2) }}{{ $taxRate ? ' (' . __('contracts.tax_vat') . ' ' . rtrim(rtrim(number_format((float) $taxRate, 2), '0'), '.') . '%)' : '' }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif

    {{-- ===================== IN WITNESS WHEREOF ===================== --}}
    <div class="sign-witness">{{ __('contracts.pdf_in_witness') }}</div>

    <table class="sign-grid">
        <tr>
            {{-- Buyer signature cell --}}
            <td class="sign-cell">
                <div class="role">{{ __('contracts.pdf_party_buyer') }}</div>
                <div class="name">{{ $buyer['name'] }}</div>

                <div class="field">{{ __('contracts.pdf_full_name') }}</div>
                <div class="line"></div>

                <div class="field">{{ __('contracts.pdf_capacity') }}</div>
                <div class="line"></div>

                <div class="field">{{ __('contracts.pdf_signature') }} / {{ __('contracts.pdf_company_stamp') }}</div>
                <div class="line" style="min-height: 36px;"></div>

                <div class="field">{{ __('contracts.pdf_date_label') }}</div>
                <div class="line"></div>

                @if($buyerSig)
                    <div class="audit">
                        <div class="label">{{ __('contracts.pdf_audit_trail') }}</div>
                        {{ __('contracts.pdf_audit_signed_by') }}: user #{{ $buyerSig['user_id'] ?? '—' }}<br>
                        {{ __('contracts.pdf_audit_acting_for') }}: {{ $buyer['name'] }}<br>
                        {{ __('contracts.pdf_audit_at') }}: {{ $buyerSig['signed_at'] ?? '—' }}
                    </div>
                @else
                    <div class="audit unsigned">
                        <div class="label">{{ __('contracts.pdf_audit_trail') }}</div>
                        {{ __('contracts.pdf_audit_unsigned') }}
                    </div>
                @endif
            </td>

            {{-- Supplier signature cell --}}
            <td class="sign-cell">
                <div class="role">{{ __('contracts.pdf_party_supplier') }}</div>
                <div class="name">{{ $supplier['name'] }}</div>

                <div class="field">{{ __('contracts.pdf_full_name') }}</div>
                <div class="line"></div>

                <div class="field">{{ __('contracts.pdf_capacity') }}</div>
                <div class="line"></div>

                <div class="field">{{ __('contracts.pdf_signature') }} / {{ __('contracts.pdf_company_stamp') }}</div>
                <div class="line" style="min-height: 36px;"></div>

                <div class="field">{{ __('contracts.pdf_date_label') }}</div>
                <div class="line"></div>

                @if($supplierSig)
                    <div class="audit">
                        <div class="label">{{ __('contracts.pdf_audit_trail') }}</div>
                        {{ __('contracts.pdf_audit_signed_by') }}: user #{{ $supplierSig['user_id'] ?? '—' }}<br>
                        {{ __('contracts.pdf_audit_acting_for') }}: {{ $supplier['name'] }}<br>
                        {{ __('contracts.pdf_audit_at') }}: {{ $supplierSig['signed_at'] ?? '—' }}
                    </div>
                @else
                    <div class="audit unsigned">
                        <div class="label">{{ __('contracts.pdf_audit_trail') }}</div>
                        {{ __('contracts.pdf_audit_unsigned') }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ===================== DISCLAIMER ===================== --}}
    <div class="disclaimer">
        {{ __('contracts.pdf_disclaimer') }}<br>
        v{{ $contract->version }} · {{ $contract->contract_number }} · {{ now()->format('Y-m-d H:i') }} UTC
    </div>

</body>
</html>
