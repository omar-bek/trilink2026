@php
    /**
     * e-Signature Audit Certificate (PDF render).
     *
     * Standalone legal document that captures every signature applied
     * to a contract along with the full evidentiary metadata required
     * by UAE Federal Decree-Law 46/2021 (Electronic Transactions Law).
     * Article 18 of that law requires that an electronic signature be:
     *   1. Uniquely linked to the signatory
     *   2. Capable of identifying the signatory
     *   3. Created using means under the signatory's sole control
     *   4. Linked to data in a way that detects tampering
     *
     * This certificate provides the audit evidence for all four:
     *   1. user_id + IP + UA → uniquely linked
     *   2. signer name, email, company → identification
     *   3. step-up password + consent timestamp → sole control
     *   4. SHA-256 contract_hash + version → tamper detection
     *
     * Pure English text for legal clarity — the contract itself is
     * bilingual but the audit trail is technical metadata that does
     * not benefit from translation.
     *
     * Sprint Hardening — colour palette extracted to PHP constants
     * at the top so a brand refresh only edits ONE place. DomPDF does
     * not honour Tailwind classes (it parses the static `<style>`
     * block at render time), so we cannot use the dashboard's CSS
     * variables here directly — but pulling the values from
     * config('brand.*') keeps a single source of truth for the day
     * the customer wants a redesign.
     */
    // Read brand colors from config when available, fall back to the
    // dark-mode tokens defined in resources/css/app.css. The fallback
    // chain means a fresh install with no `config/brand.php` still
    // renders correctly, while a customer-customised palette only
    // needs one config edit.
    $brand = [
        'ink'           => config('brand.pdf.ink',           '#0f1117'),
        'ink_soft'      => config('brand.pdf.ink_soft',      '#1a1d29'),
        'text_muted'    => config('brand.pdf.text_muted',    '#4f5366'),
        'text_faint'    => config('brand.pdf.text_faint',    '#6b7080'),
        'border'        => config('brand.pdf.border',        '#d8dce6'),
        'border_soft'   => config('brand.pdf.border_soft',   '#e8ebf4'),
        'accent_info'   => config('brand.pdf.accent_info',   '#4f7cff'),
        'accent_success'=> config('brand.pdf.accent_success','#00d9b5'),
        'accent_warning'=> config('brand.pdf.accent_warning','#ffb020'),
        'tint_blue'     => config('brand.pdf.tint_blue',     '#f6f8ff'),
        'tint_amber'    => config('brand.pdf.tint_amber',    '#fff8eb'),
        'tint_amber_ink'=> config('brand.pdf.tint_amber_ink','#6b5320'),
        'tint_amber_h'  => config('brand.pdf.tint_amber_h',  '#b87a0e'),
        'tint_green'    => config('brand.pdf.tint_green',    '#f0fdf9'),
        'tint_green_ink'=> config('brand.pdf.tint_green_ink','#1a3d36'),
        'tint_grey'     => config('brand.pdf.tint_grey',     '#f4f6fb'),
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>e-Signature Audit Certificate — {{ $contract->contract_number }}</title>
    <style>
        @page { margin: 60px 50px 70px 50px; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: {{ $brand['ink_soft'] }};
        }
        .doc-header {
            border-bottom: 2px solid {{ $brand['ink'] }};
            padding-bottom: 14px;
            margin-bottom: 24px;
            text-align: center;
        }
        .doc-header .brand {
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: {{ $brand['accent_info'] }};
            font-weight: 700;
            margin-bottom: 6px;
        }
        .doc-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: {{ $brand['ink'] }};
            margin: 0 0 4px 0;
            letter-spacing: -0.4px;
        }
        .doc-header .subtitle {
            font-size: 10.5px;
            color: {{ $brand['text_muted'] }};
            margin-bottom: 8px;
        }
        .meta-strip {
            background: {{ $brand['tint_blue'] }};
            border: 1px solid {{ $brand['border'] }};
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 10px;
        }
        .meta-strip table { width: 100%; }
        .meta-strip td { padding: 2px 0; }
        .meta-strip td.k { color: {{ $brand['text_faint'] }}; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 9px; width: 35%; }
        .meta-strip td.v { color: {{ $brand['ink'] }}; font-weight: 600; }

        .legal-disclosure {
            background: {{ $brand['tint_amber'] }};
            border-left: 3px solid {{ $brand['accent_warning'] }};
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 10px;
            color: {{ $brand['tint_amber_ink'] }};
            line-height: 1.65;
        }
        .legal-disclosure .head {
            font-weight: 700;
            color: {{ $brand['tint_amber_h'] }};
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 9px;
            margin-bottom: 4px;
            display: block;
        }

        .event-card {
            border: 1px solid {{ $brand['border'] }};
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 14px;
            background: #fff;
            page-break-inside: avoid;
        }
        .event-card .header {
            border-bottom: 1px solid {{ $brand['border_soft'] }};
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .event-card .header .num {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            background: {{ $brand['accent_success'] }};
            color: #fff;
            border-radius: 50%;
            font-weight: 700;
            font-size: 10px;
            margin-right: 8px;
        }
        .event-card .header .title {
            font-weight: 700;
            font-size: 12px;
            color: {{ $brand['ink'] }};
            display: inline;
        }
        .event-card .header .when {
            float: right;
            color: {{ $brand['text_muted'] }};
            font-size: 10px;
            font-weight: 600;
        }

        .event-card table.meta { width: 100%; font-size: 10px; }
        .event-card table.meta td { padding: 2px 0; vertical-align: top; }
        .event-card table.meta td.k {
            color: {{ $brand['text_faint'] }};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 9px;
            width: 30%;
        }
        .event-card table.meta td.v { color: {{ $brand['ink'] }}; font-weight: 600; word-break: break-all; }

        .event-card .consent {
            margin-top: 10px;
            padding: 8px 10px;
            background: {{ $brand['tint_green'] }};
            border-left: 3px solid {{ $brand['accent_success'] }};
            border-radius: 3px;
            font-size: 9.5px;
            color: {{ $brand['tint_green_ink'] }};
            font-style: italic;
        }
        .event-card .hash {
            font-family: monospace;
            font-size: 9px;
            background: {{ $brand['tint_grey'] }};
            padding: 2px 4px;
            border-radius: 3px;
            color: {{ $brand['text_muted'] }};
        }

        .empty {
            text-align: center;
            padding: 40px 20px;
            font-size: 12px;
            color: {{ $brand['text_faint'] }};
            font-style: italic;
        }

        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid {{ $brand['border'] }};
            font-size: 9px;
            color: {{ $brand['text_faint'] }};
            text-align: center;
            line-height: 1.65;
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="brand">Trilink Procurement Platform</div>
        <h1>e-Signature Audit Certificate</h1>
        <div class="subtitle">Cryptographic evidence of contract execution under UAE Federal Decree-Law No. 46 of 2021</div>
    </div>

    <div class="meta-strip">
        <table>
            <tr>
                <td class="k">Contract Number</td>
                <td class="v">{{ $contract->contract_number }}</td>
            </tr>
            <tr>
                <td class="k">Title</td>
                <td class="v">{{ $contract->title }}</td>
            </tr>
            <tr>
                <td class="k">Total Value</td>
                <td class="v">{{ $contract->currency ?? 'AED' }} {{ number_format((float) $contract->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="k">Current Version</td>
                <td class="v">v{{ $contract->version }}</td>
            </tr>
            <tr>
                <td class="k">Certificate Generated</td>
                <td class="v">{{ $generated_at }}</td>
            </tr>
            <tr>
                <td class="k">Total Signatures</td>
                <td class="v">{{ count($events) }}</td>
            </tr>
        </table>
    </div>

    <div class="legal-disclosure">
        <span class="head">Legal Disclosure</span>
        This certificate constitutes documentary evidence of electronic signatures
        applied to the above contract. The metadata captured below — including IP
        address, device fingerprint (User-Agent), authenticated session, consent
        statement and SHA-256 hash of the contract terms at the moment of signing —
        establishes that each signature was uniquely linked to its signatory and
        applied with full knowledge of the contract content. Per Article 18 of UAE
        Federal Decree-Law No. 46 of 2021 (Electronic Transactions Law), an
        electronic signature meeting these conditions has the same legal force as
        a handwritten signature before UAE courts.
    </div>

    @forelse($events as $idx => $event)
        <div class="event-card">
            <div class="header">
                <span class="num">{{ $idx + 1 }}</span>
                <span class="title">{{ $event['signer_name'] }} — {{ $event['company_name'] }}</span>
                <span class="when">{{ $event['signed_at'] }}</span>
            </div>
            <table class="meta">
                <tr>
                    <td class="k">Signer</td>
                    <td class="v">{{ $event['signer_name'] }}</td>
                </tr>
                <tr>
                    <td class="k">Email</td>
                    <td class="v">{{ $event['signer_email'] }}</td>
                </tr>
                <tr>
                    <td class="k">Acting for</td>
                    <td class="v">{{ $event['company_name'] }}</td>
                </tr>
                <tr>
                    <td class="k">Company TRN</td>
                    <td class="v">{{ $event['company_trn'] }}</td>
                </tr>
                <tr>
                    <td class="k">Signed at (UTC)</td>
                    <td class="v">{{ $event['signed_at'] }}</td>
                </tr>
                <tr>
                    <td class="k">IP Address</td>
                    <td class="v">{{ $event['ip_address'] }}</td>
                </tr>
                <tr>
                    <td class="k">Device / User-Agent</td>
                    <td class="v">{{ $event['user_agent'] }}</td>
                </tr>
                <tr>
                    <td class="k">Contract Version</td>
                    <td class="v">v{{ $event['contract_version'] }}</td>
                </tr>
                <tr>
                    <td class="k">Terms Hash (SHA-256)</td>
                    <td class="v"><span class="hash">{{ $event['contract_hash'] }}</span></td>
                </tr>
                <tr>
                    <td class="k">Consent At</td>
                    <td class="v">{{ $event['consent_at'] }}</td>
                </tr>
            </table>
            @if(!empty($event['consent_text']) && $event['consent_text'] !== '—')
            <div class="consent">
                <strong>Acknowledged statement:</strong> "{{ $event['consent_text'] }}"
            </div>
            @endif
        </div>
    @empty
        <div class="empty">
            No signatures have been applied to this contract yet.
        </div>
    @endforelse

    <div class="footer">
        Generated by Trilink Procurement Platform — {{ $generated_at }}<br>
        This document is machine-generated and forms part of the legal audit
        trail of contract {{ $contract->contract_number }}. The cryptographic
        SHA-256 hash above can be independently verified by recomputing it
        against the contract terms at the corresponding version.
    </div>
</body>
</html>
