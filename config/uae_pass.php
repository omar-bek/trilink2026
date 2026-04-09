<?php

/**
 * UAE Pass — Federal Decree-Law 46/2021 compliant identity assertion
 * for the contract signing flow. UAE Pass is the national digital
 * identity issued by TDRA in partnership with Smart Dubai, ADDA and
 * the Federal Authority for Identity, Citizenship, Customs & Port
 * Security. A signature backed by a UAE Pass assertion satisfies
 * the "Advanced" grade under Article 18 because the signing user is
 * uniquely identified by their Emirates ID via a government-verified
 * channel.
 *
 * Production credentials must be requested from selfcare.uaepass.ae
 * after the company is registered as a Service Provider with TDRA —
 * the dev sandbox is open immediately.
 *
 * The platform talks to UAE Pass via an OAuth 2.0 Authorization Code
 * flow. After the user lands back on our callback we exchange the
 * code for an access token and pull the user's profile (full name,
 * Emirates ID hash, mobile). We never store the Emirates ID number in
 * plaintext — only its hash, used for cross-tenant duplicate detection.
 */

return [
    // Hard-disable the integration when no credentials are configured.
    // The contract sign modal hides the "Sign with UAE Pass" CTA when
    // this is false; the verify page also short-circuits to the
    // platform-only audit trail.
    'enabled' => env('UAE_PASS_ENABLED', false),

    // 'sandbox' or 'production'. Sandbox uses stg-id.uaepass.ae which
    // accepts test users without a real Emirates ID; production uses
    // id.uaepass.ae and requires a TDRA-issued client_id.
    'environment' => env('UAE_PASS_ENV', 'sandbox'),

    'client_id'     => env('UAE_PASS_CLIENT_ID'),
    'client_secret' => env('UAE_PASS_CLIENT_SECRET'),

    // OAuth scopes the consent screen asks for. `urn:uae:digitalid:profile`
    // returns the user's full name, Emirates ID hash and verified
    // status. Adding more scopes requires re-applying with TDRA.
    'scope' => env('UAE_PASS_SCOPE', 'urn:uae:digitalid:profile:general'),

    // Used by the OAuth state parameter to bind the redirect to the
    // user's session — prevents CSRF on the callback. The Laravel
    // session id is the natural value; the controller injects it.
    'redirect_uri' => env('UAE_PASS_REDIRECT_URI'),

    // Endpoints — pre-filled per environment. Don't override unless
    // UAE Pass publishes a breaking change.
    'endpoints' => [
        'sandbox' => [
            'authorize' => 'https://stg-id.uaepass.ae/idshub/authorize',
            'token'     => 'https://stg-id.uaepass.ae/idshub/token',
            'userinfo'  => 'https://stg-id.uaepass.ae/idshub/userinfo',
        ],
        'production' => [
            'authorize' => 'https://id.uaepass.ae/idshub/authorize',
            'token'     => 'https://id.uaepass.ae/idshub/token',
            'userinfo'  => 'https://id.uaepass.ae/idshub/userinfo',
        ],
    ],

    // ACR (Authentication Context Class Reference) — UAE Pass supports:
    //   urn:safelayer:tws:policies:authentication:level:low      (SMS OTP)
    //   urn:digitalid:authentication:flow:mobileondevice         (UAE Pass app push)
    //   urn:safelayer:tws:policies:authentication:level:high     (face match)
    // We default to "high" for contract signing because Article 18
    // requires the signature be under the signatory's sole control.
    'acr_values' => env('UAE_PASS_ACR', 'urn:safelayer:tws:policies:authentication:level:high'),

    // Cache TTL for the OAuth state parameter. 10 minutes is generous
    // enough for the user to complete the consent screen + push prompt.
    'state_ttl_seconds' => 600,

    // Trust Service Provider abstraction — which TSP to use when the
    // contract requires a Qualified grade. UAE Pass alone is Advanced;
    // a Qualified signature requires a TDRA-accredited TSP issuing a
    // long-lived signing certificate. Mock by default.
    'qualified_tsp_provider' => env('UAE_PASS_QUALIFIED_TSP', 'mock'),
];
