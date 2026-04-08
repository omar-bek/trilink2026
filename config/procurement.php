<?php

/**
 * Procurement-domain configuration that's not third-party credentials.
 * Lives in config/ rather than .env so the values are version-controlled
 * and admins don't need a deployment to tweak them.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Available Payment Methods
    |--------------------------------------------------------------------------
    |
    | Shown to suppliers in the bid detail card and to buyers in the
    | payment configuration screen. Add a new entry here to expose it
    | platform-wide; remove one to retire it without touching code.
    |
    */
    'payment_methods' => [
        'Bank Transfer',
        'Letter of Credit',
        'Trade Finance',
        'Escrow',
    ],

];
