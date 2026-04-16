<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    | Supported: "vonage", "twilio", "log"
    */
    'provider' => env('SMS_PROVIDER', 'log'),

    'vonage' => [
        'key'      => env('VONAGE_KEY'),
        'secret'   => env('VONAGE_SECRET'),
        'sms_from' => env('VONAGE_SMS_FROM', 'TriLink'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],
];
