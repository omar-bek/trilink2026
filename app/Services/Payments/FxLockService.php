<?php

namespace App\Services\Payments;

use App\Models\ExchangeRate;
use App\Models\Payment;

/**
 * Freezes the FX rate on a Payment at approval time so the value that
 * actually leaves the buyer's hand doesn't drift with the market between
 * approval and settlement.
 *
 * Base currency defaults to AED (the platform's reporting currency). A
 * Payment already in AED still gets fx_rate_snapshot = 1.0 so downstream
 * consumers don't have to branch on "is this cross-currency?".
 */
class FxLockService
{
    public const BASE = 'AED';

    /**
     * Apply the snapshot in-memory (caller saves). Idempotent: once
     * fx_locked_at is set, repeated calls are no-ops so re-approval
     * flows don't silently shift the rate.
     */
    public function lock(Payment $payment, ?\Carbon\CarbonInterface $asOf = null): void
    {
        if ($payment->fx_locked_at) {
            return;
        }

        $asOf ??= now();
        $currency = strtoupper((string) ($payment->currency ?? self::BASE));
        $rate = $currency === self::BASE ? 1.0 : (float) ExchangeRate::convert(1.0, $currency, self::BASE, $asOf);

        $payment->fx_rate_snapshot = $rate;
        $payment->fx_base_currency = self::BASE;
        $payment->fx_locked_at = $asOf;
        $payment->amount_in_base = round(((float) $payment->amount) * $rate, 2);
    }
}
