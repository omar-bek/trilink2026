<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Virus Scan Driver
    |--------------------------------------------------------------------------
    | Supported: "clamav", "virustotal", "none"
    | Use "none" only in local development.
    */
    'driver' => env('VIRUS_SCAN_DRIVER', 'none'),

    'clamav' => [
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => env('CLAMAV_PORT', 3310),
    ],

    'virustotal' => [
        'key' => env('VIRUSTOTAL_API_KEY'),
    ],
];
