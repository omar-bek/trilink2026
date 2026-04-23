<?php

return [
    // Max counter-offer rounds per bid. Bid-level override via
    // bids.negotiation_round_cap takes precedence when set.
    'default_round_cap' => env('NEGOTIATION_DEFAULT_ROUND_CAP', 5),

    // How many UAE business days a counter-offer stays open before the
    // sweeper auto-rejects it. Weekends + federal holidays are honoured.
    'expiry_business_days' => env('NEGOTIATION_EXPIRY_BUSINESS_DAYS', 2),

    // AED contract value at and above which the escrow account is opened
    // automatically the moment the contract is created from an accepted
    // bid. Non-AED contracts don't auto-trigger (FX is a finance decision).
    'escrow_auto_threshold_aed' => env('NEGOTIATION_ESCROW_THRESHOLD_AED', 50000),

    // AED contract value at and above which finance gets pinged to chase
    // a Performance Bond from the supplier's bank. Advisory only — the
    // actual BG is registered through the BankGuaranteeService.
    'bg_advisory_threshold_aed' => env('NEGOTIATION_BG_ADVISORY_THRESHOLD_AED', 250000),
];
