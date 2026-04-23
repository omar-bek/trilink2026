<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\PlatformFeeAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Computes the platform's take on each payment the moment it transitions
 * to APPROVED. One allocation row per fee type so the finance export can
 * break out "escrow custody fee" from "transaction processing fee".
 *
 * The rates live in the platform_fees table (seeded per tier, per fee
 * type). Idempotent: duplicate calls for the same (payment, fee_type)
 * are a no-op thanks to the unique index in code.
 */
class PlatformFeeCalculator
{
    public function allocate(Payment $payment): void
    {
        $base = (float) $payment->amount;
        if ($base <= 0) {
            return;
        }

        $currency = strtoupper((string) ($payment->currency ?? 'AED'));

        $rates = $this->loadRates();

        DB::transaction(function () use ($payment, $base, $currency, $rates) {
            foreach ($rates as $feeType => $rate) {
                if ($rate <= 0) {
                    continue;
                }
                PlatformFeeAllocation::query()->updateOrCreate(
                    ['payment_id' => $payment->id, 'fee_type' => $feeType],
                    [
                        'base_amount' => round($base, 2),
                        'rate' => $rate,
                        'fee_amount' => round($base * $rate, 2),
                        'currency' => $currency,
                    ],
                );
            }
        });
    }

    /**
     * @return array<string,float>
     */
    private function loadRates(): array
    {
        // PlatformFee may not be migrated yet in every tenant; fall back
        // to config values so the service never breaks the approve flow.
        $rates = [
            PlatformFeeAllocation::TYPE_TRANSACTION => (float) config('payments.fees.transaction', 0.0125),
            PlatformFeeAllocation::TYPE_ESCROW => (float) config('payments.fees.escrow', 0.005),
            PlatformFeeAllocation::TYPE_RECON => (float) config('payments.fees.recon', 0.0),
            PlatformFeeAllocation::TYPE_LISTING => (float) config('payments.fees.listing', 0.0),
        ];

        if (class_exists(PlatformFee::class) && \Schema::hasTable('platform_fees')) {
            foreach (PlatformFee::query()->where('active', true)->get() as $row) {
                $key = (string) ($row->fee_type ?? '');
                if ($key === '') {
                    continue;
                }
                $rates[$key] = (float) $row->rate;
            }
        }

        return $rates;
    }
}
