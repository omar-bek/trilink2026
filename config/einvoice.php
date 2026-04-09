<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Phase 5 (UAE Compliance Roadmap) — when this flag is FALSE the
    | entire e-invoicing pipeline is dormant: no submissions are
    | created when a tax invoice is issued, no jobs are dispatched,
    | the admin queue still renders but doesn't allow new actions.
    |
    | This is the safety pin. Default OFF until the FTA Phase 1
    | go-live in July 2026 — flipping it on without a configured
    | provider would just queue rows that the mock provider has to
    | handle anyway, which is fine for testing but undesirable in
    | production.
    |
    */

    'enabled' => env('EINVOICE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default ASP provider
    |--------------------------------------------------------------------------
    |
    | The provider key the EInvoiceDispatcher uses when no per-tenant
    | override exists. Allowed values are the keys in `providers`
    | below. `mock` is a local-only provider that produces valid UBL
    | 2.1 PINT-AE XML and returns a fake clearance id — use it for
    | tests, demos, and to keep the pipeline running before the real
    | ASP credentials land.
    |
    */

    'default_provider' => env('EINVOICE_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | sandbox | production. Stamped onto every submission row so we
    | can tell test transmissions apart from real ones in the audit
    | trail.
    |
    */

    'environment' => env('EINVOICE_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Provider registry
    |--------------------------------------------------------------------------
    |
    | Each entry maps a provider key to its concrete class + the
    | (env-driven) credentials it needs to talk to its API. The
    | dispatcher resolves the class via the container so binding a
    | replacement at runtime (for tests) is one app->bind() call.
    |
    */

    'providers' => [

        'mock' => [
            'class'   => \App\Services\EInvoice\MockAspProvider::class,
            'enabled' => true,
        ],

        'avalara' => [
            'class'    => \App\Services\EInvoice\AvalaraAspProvider::class,
            'enabled'  => env('EINVOICE_AVALARA_ENABLED', false),
            'base_url' => env('EINVOICE_AVALARA_BASE_URL', 'https://api.sbx.avalara.com/einvoicing/v1'),
            'api_key'  => env('EINVOICE_AVALARA_API_KEY'),
        ],

        'sovos' => [
            'class'    => \App\Services\EInvoice\SovosAspProvider::class,
            'enabled'  => env('EINVOICE_SOVOS_ENABLED', false),
            'base_url' => env('EINVOICE_SOVOS_BASE_URL', 'https://api.sandbox.sovos.com/einvoicing'),
            'api_key'  => env('EINVOICE_SOVOS_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry policy
    |--------------------------------------------------------------------------
    |
    | The queue worker retries failed submissions with exponential
    | backoff: 1m, 5m, 30m, 2h, 8h. After max_retries the row stays
    | in `failed` and the admin must intervene.
    |
    */

    'max_retries' => env('EINVOICE_MAX_RETRIES', 5),

    'backoff_minutes' => [1, 5, 30, 120, 480],

    /*
    |--------------------------------------------------------------------------
    | Webhook secret
    |--------------------------------------------------------------------------
    |
    | Shared secret the ASP signs its async callbacks with. The webhook
    | controller validates the X-Signature header against an HMAC of
    | the request body using this secret. Without it, the controller
    | rejects every callback as 503 — that prevents an attacker from
    | flipping a submission to "accepted" by guessing the URL.
    |
    */

    'webhook_secret' => env('EINVOICE_WEBHOOK_SECRET'),
];
