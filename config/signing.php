<?php

use App\Services\Signing\ComtrustTspProvider;
use App\Services\Signing\EsspTspProvider;
use App\Services\Signing\MockTspProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Advanced grade threshold
    |--------------------------------------------------------------------------
    |
    | Phase 6 (UAE Compliance Roadmap). Federal Decree-Law 46/2021 doesn't
    | name a specific value threshold for Advanced signatures, but UAE
    | legal practice consistently treats AED 500,000 as the floor: above
    | this, courts expect more than a Simple signature when adjudicating
    | a dispute.
    |
    | Tenants can tighten this (set it lower) by overriding the env var.
    | Setting it to 0 forces Advanced on every contract — useful for
    | enterprise tenants who never want a Simple signature on their
    | books regardless of value.
    |
    */

    'advanced_threshold_aed' => env('SIGNING_ADVANCED_THRESHOLD_AED', 500_000),

    /*
    |--------------------------------------------------------------------------
    | Qualified-grade keywords
    |--------------------------------------------------------------------------
    |
    | When ANY of these substrings appears in the contract title, the
    | resolver upgrades the required grade to QUALIFIED. The list is
    | a pragmatic backstop until the platform normalises categories in
    | Phase 8 — at that point we'll switch to a category_id allowlist.
    |
    | Real estate, insurance, financial services and securities all
    | require Qualified signatures under UAE law regardless of value:
    |
    |   - Real estate / lease / mortgage — Federal Law 8/2007 (RERA)
    |   - Insurance contracts — Federal Decree-Law 48/2023 Article 12
    |   - Securities & investment funds — SCA Decision 13/2021
    |
    */

    'qualified_keywords' => [
        'real estate',
        'property',
        'lease',
        'tenancy',
        'mortgage',
        'insurance',
        'reinsurance',
        'takaful',
        'securities',
        'investment fund',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trust Service Providers
    |--------------------------------------------------------------------------
    |
    | Registry of TDRA-accredited providers the platform can use to issue
    | a Qualified signature. Each entry maps a slug to a concrete class +
    | the (env-driven) credentials it needs.
    |
    | The default `mock` provider is for tests + the period before any
    | commercial TSP contract is signed — it produces a deterministic
    | fake CAdES envelope so the rest of the pipeline can be exercised
    | end-to-end. Comtrust + ESSP are real TDRA-accredited providers;
    | the skeleton classes throw on submit() until the env vars are set.
    |
    */

    'tsp_providers' => [

        'mock' => [
            'class' => MockTspProvider::class,
            'enabled' => true,
        ],

        'comtrust' => [
            'class' => ComtrustTspProvider::class,
            'enabled' => env('SIGNING_COMTRUST_ENABLED', false),
            'base_url' => env('SIGNING_COMTRUST_BASE_URL', 'https://api.comtrust.ae/v1'),
            'api_key' => env('SIGNING_COMTRUST_API_KEY'),
        ],

        'essp' => [
            'class' => EsspTspProvider::class,
            'enabled' => env('SIGNING_ESSP_ENABLED', false),
            'base_url' => env('SIGNING_ESSP_BASE_URL', 'https://api.essp.ae/v1'),
            'api_key' => env('SIGNING_ESSP_API_KEY'),
        ],
    ],

    'default_tsp_provider' => env('SIGNING_TSP_PROVIDER', 'mock'),
];
