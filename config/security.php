<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security headers
    |--------------------------------------------------------------------------
    */

    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),

    // Note: script-src allows 'unsafe-inline' because 24 Blade templates
    // contain inline <script> blocks (theme toggle, sidebar, flash messages,
    // etc.) and 'unsafe-eval' because Alpine.js (bundled in Livewire) uses
    // `new Function()` to evaluate directive expressions such as x-data,
    // x-show, @click. Without it, ALL Alpine components silently break —
    // tabs, radio groups, <template x-for>, @click handlers, etc. A future
    // nonce-based refactor plus Alpine's CSP build can remove both.
    'csp' => env('SECURITY_CSP', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data: https://fonts.bunny.net; connect-src 'self'; frame-ancestors 'none'"),

    /*
    |--------------------------------------------------------------------------
    | Admin IP allowlist
    |--------------------------------------------------------------------------
    */

    'admin_ip_allowlist' => env('SECURITY_ADMIN_IP_ALLOWLIST', ''),

    /*
    |--------------------------------------------------------------------------
    | Audit chain external anchor
    |--------------------------------------------------------------------------
    */

    'audit_anchor_s3_disk' => env('SECURITY_AUDIT_ANCHOR_S3_DISK'),
];
