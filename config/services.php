<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal
    |--------------------------------------------------------------------------
    |
    | PayPal webhook signature verification uses a shared secret HMAC-SHA256
    | over the raw request body. The webhook_secret is provisioned in the
    | PayPal merchant dashboard and rotated independently of the API
    | credentials. Without a secret configured the webhook endpoint refuses
    | every request — fail closed, never open.
    |
    */
    'paypal' => [
        'client_id'      => env('PAYPAL_CLIENT_ID'),
        'secret'         => env('PAYPAL_SECRET'),
        'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        // Per-bank webhook HMAC secrets for the escrow partner endpoints.
        // Mirrored under services.escrow.<partner>.webhook_secret so the
        // bank-partner adapters can use the same env names.
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Carriers
    |--------------------------------------------------------------------------
    |
    | Per-carrier credentials for the App\Services\Shipping adapters. Any
    | carrier with empty credentials falls back to a deterministic mock so
    | demos and tests still work end-to-end without commercial accounts.
    |
    */
    'carriers' => [
        'aramex' => [
            'username'       => env('ARAMEX_USERNAME'),
            'password'       => env('ARAMEX_PASSWORD'),
            'account_number' => env('ARAMEX_ACCOUNT_NUMBER'),
            'account_pin'    => env('ARAMEX_ACCOUNT_PIN'),
            'account_entity' => env('ARAMEX_ACCOUNT_ENTITY', 'DXB'),
            'account_country'=> env('ARAMEX_ACCOUNT_COUNTRY', 'AE'),
        ],
        'dhl' => [
            'api_key'    => env('DHL_API_KEY'),
            'api_secret' => env('DHL_API_SECRET'),
            'account'    => env('DHL_ACCOUNT'),
        ],
        'fedex' => [
            'client_id'     => env('FEDEX_CLIENT_ID'),
            'client_secret' => env('FEDEX_CLIENT_SECRET'),
            'account'       => env('FEDEX_ACCOUNT'),
        ],
        'ups' => [
            'client_id'     => env('UPS_CLIENT_ID'),
            'client_secret' => env('UPS_CLIENT_SECRET'),
            'account'       => env('UPS_ACCOUNT'),
        ],
        'fetchr' => [
            'api_key' => env('FETCHR_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic Claude
    |--------------------------------------------------------------------------
    |
    | Used by classification (HS code suggestion), contract analysis, and
    | the procurement AI copilot. When the key is missing the system falls
    | back to deterministic rule-based heuristics.
    |
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 3 — Escrow Bank Partners
    |--------------------------------------------------------------------------
    |
    | Provider config for App\Services\Escrow adapters. The active default
    | is selected by `escrow.default`; once a partnership is signed and the
    | API key lands in the env, switch the default to that provider's key
    | (e.g. 'mashreq_neobiz') and every newly-activated escrow account
    | flips over without a code change. Existing accounts keep using the
    | provider they were opened against — see EscrowAccount::bank_partner.
    |
    */
    'escrow' => [
        'default' => env('ESCROW_DEFAULT_PROVIDER', 'mock'),
        'mashreq' => [
            'api_key'        => env('ESCROW_MASHREQ_API_KEY'),
            'base_url'       => env('ESCROW_MASHREQ_BASE_URL'),
            'timeout'        => env('ESCROW_MASHREQ_TIMEOUT', 12),
            // HMAC-SHA256 shared secret for the inbound webhook posted by
            // the bank when a wire clears. Required — the controller
            // refuses requests for any partner that has no secret.
            'webhook_secret' => env('ESCROW_MASHREQ_WEBHOOK_SECRET'),
        ],
        'enbd' => [
            'api_key'        => env('ESCROW_ENBD_API_KEY'),
            'base_url'       => env('ESCROW_ENBD_BASE_URL'),
            'timeout'        => env('ESCROW_ENBD_TIMEOUT', 12),
            'webhook_secret' => env('ESCROW_ENBD_WEBHOOK_SECRET'),
        ],
        'mock' => [
            // The mock provider intentionally has no real secret — the
            // controller short-circuits the signature check for it so
            // local development and test suites work without env vars.
            'webhook_secret' => env('ESCROW_MOCK_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 6 — Dubai Trade Portal
    |--------------------------------------------------------------------------
    |
    | B2G integration with Dubai Trade for customs declarations. Without
    | a key the DubaiTradeAdapter falls back to a stub that returns a
    | deterministic declaration reference so demos work end-to-end.
    |
    */
    'dubai_trade' => [
        'api_key'  => env('DUBAI_TRADE_API_KEY'),
        'base_url' => env('DUBAI_TRADE_BASE_URL', 'https://api.sandbox.dubaitrade.ae/customs/v1'),
        'timeout'  => env('DUBAI_TRADE_TIMEOUT', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 3 — Open Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Daily FX rates feed for the multi-currency contract display. The
    | `fx:sync` command pulls a snapshot, dumps it into the exchange_rates
    | table, and the ExchangeRate model serves conversions from there.
    | Without an app_id the command falls back to a static seed of common
    | GCC/USD/EUR rates so the UI never shows broken converted prices.
    |
    */
    'openexchangerates' => [
        'app_id'  => env('OPENEXCHANGERATES_APP_ID'),
        'base'    => env('OPENEXCHANGERATES_BASE', 'USD'),
        'timeout' => env('OPENEXCHANGERATES_TIMEOUT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanctions Screening Providers
    |--------------------------------------------------------------------------
    |
    | Provider-specific config for App\Services\Sanctions adapters. The
    | active provider is bound in App\Providers\AppServiceProvider. Each
    | provider's API key is optional — without it OpenSanctions still
    | works on the free public tier (rate-limited).
    |
    */
    'sanctions' => [
        'opensanctions' => [
            'api_key' => env('OPENSANCTIONS_API_KEY'),
            'timeout' => env('OPENSANCTIONS_TIMEOUT', 8),
        ],
        // Phase 3: Refinitiv World-Check One for enterprise customers.
        'refinitiv' => [
            'api_key'   => env('REFINITIV_API_KEY'),
            'api_secret'=> env('REFINITIV_API_SECRET'),
            'endpoint'  => env('REFINITIV_ENDPOINT', 'https://api.refinitiv.com/wco/v1'),
        ],
    ],

];
