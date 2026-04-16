<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hosting region
    |--------------------------------------------------------------------------
    |
    | The country (ISO 3166-1 alpha-2) where the production database is
    | hosted. Surfaced verbatim in:
    |
    |   - The privacy policy ("Where your data lives")
    |   - The DPA template (Schedule 1 — Sub-processors)
    |   - The DSAR archive index.json (so the data subject can verify
    |     residency themselves)
    |
    | Federal Decree-Law 45/2021 Article 22 restricts cross-border transfers
    | of UAE personal data unless one of the following bases applies:
    |
    |   - The destination is on the UAE Data Office's adequacy list
    |   - Standard Contractual Clauses (SCCs) signed with the processor
    |   - Explicit consent from the data subject
    |   - Necessary for the performance of a contract
    |
    | The `adequacy_basis` setting below documents which basis the
    | platform is relying on. If you flip the hosting region, update
    | this value AND notify the legal team — the basis may need to
    | change too.
    |
    */

    'region' => env('DATA_RESIDENCY_REGION', 'AE'),

    /*
    |--------------------------------------------------------------------------
    | Adequacy basis
    |--------------------------------------------------------------------------
    |
    | One of: in_country | adequacy_decision | scc | consent | contract_necessity
    |
    | - in_country         — Data stays in the UAE. No cross-border transfer.
    | - adequacy_decision  — Destination has UAE Data Office adequacy.
    | - scc                — Standard Contractual Clauses signed (date below).
    | - consent            — Per-user explicit consent (rare for B2B).
    | - contract_necessity — Transfer necessary to fulfil the user's contract.
    |
    */

    'adequacy_basis' => env('DATA_RESIDENCY_BASIS', 'in_country'),

    /*
    |--------------------------------------------------------------------------
    | SCC signing date
    |--------------------------------------------------------------------------
    |
    | If `adequacy_basis = scc`, this is the ISO date the SCCs were signed
    | with the data processor. Used in the DPA template footer.
    |
    */

    'scc_signed_at' => env('DATA_RESIDENCY_SCC_SIGNED_AT', null),

    /*
    |--------------------------------------------------------------------------
    | Sub-processors
    |--------------------------------------------------------------------------
    |
    | Public list of every third party that processes personal data on
    | TriLink's behalf. Listed verbatim in the DPA Schedule 1 and the
    | privacy policy. PDPL Article 21 requires the controller to disclose
    | sub-processors and their location to data subjects on request.
    |
    | Each entry: name, purpose, location (ISO 3166-1 alpha-2), basis.
    |
    */

    'sub_processors' => [
        [
            'name' => 'AWS Middle East (Bahrain)',
            'purpose' => 'Database, file storage, application hosting',
            'location' => 'BH',
            'basis' => 'adequacy_decision',
        ],
        [
            'name' => 'Stripe Payments',
            'purpose' => 'Card payment processing',
            'location' => 'US',
            'basis' => 'scc',
        ],
        [
            'name' => 'OpenSanctions',
            'purpose' => 'Sanctions list screening (KYC/AML)',
            'location' => 'DE',
            'basis' => 'contract_necessity',
        ],
        [
            'name' => 'Anthropic Claude API',
            'purpose' => 'AI-assisted document classification + chat assistant',
            'location' => 'US',
            'basis' => 'scc',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Protection Officer
    |--------------------------------------------------------------------------
    |
    | Contact details for the DPO. PDPL Article 10 requires controllers
    | meeting certain thresholds to appoint a DPO and publish their
    | contact details. Even when not required, having a contact endpoint
    | is best practice and gives data subjects a clear escalation path.
    |
    */

    'dpo' => [
        'name' => env('DPO_NAME', 'Data Protection Officer'),
        'email' => env('DPO_EMAIL', 'privacy@trilink.ae'),
        'phone' => env('DPO_PHONE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy policy version
    |--------------------------------------------------------------------------
    |
    | Bump this string every time you publish a meaningful change to the
    | privacy policy. The ConsentLedger stamps this version on every
    | grant so we can later identify users who consented to v1.0 and
    | re-prompt them when v1.1 ships.
    |
    */

    'privacy_policy_version' => env('PRIVACY_POLICY_VERSION', '1.0'),
    'dpa_version' => env('DPA_VERSION', '1.0'),
];
