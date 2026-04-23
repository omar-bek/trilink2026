<?php

return [
    // AED threshold above which a payment requires a second signer.
    // Contract-level override via contracts.dual_approval_threshold_aed.
    'dual_approval_threshold_aed' => env('PAYMENTS_DUAL_APPROVAL_AED', 500000),

    // Platform fee rates (as decimals — 0.0125 = 1.25%). Per fee type.
    'fees' => [
        'transaction' => env('PAYMENTS_FEE_TRANSACTION', 0.0125),
        'escrow' => env('PAYMENTS_FEE_ESCROW', 0.005),
        'recon' => env('PAYMENTS_FEE_RECON', 0.0),
        'listing' => env('PAYMENTS_FEE_LISTING', 0.0),
    ],

    // Late-fee interest ceiling (annual). Hard-capped at 12% per UAE
    // Civil Code Article 76 & Federal Law 18/1993.
    'late_fee_annual_cap' => 12.0,

    // Default dispute window (days) after which a settled payment can
    // no longer be disputed through the platform. Can be overridden per
    // contract on payments.dispute_window_days.
    'default_dispute_window_days' => env('PAYMENTS_DISPUTE_WINDOW', 14),
];
