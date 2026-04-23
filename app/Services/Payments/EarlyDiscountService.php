<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentTermsService;
use Carbon\Carbon;

/**
 * Auto-applies the contract's early-payment discount when a payment is
 * settled inside the discount window. Called from the process() success
 * path so the discount lands on the Payment row (not just displayed).
 */
class EarlyDiscountService
{
    public function __construct(private readonly PaymentTermsService $terms) {}

    public function applyOnSettlement(Payment $payment, ?Carbon $settlementDate = null): float
    {
        $settlementDate ??= now();

        $discount = (float) $this->terms->computeEarlyDiscount($payment, $settlementDate);
        if ($discount <= 0) {
            return 0.0;
        }

        $payment->update([
            'early_discount_amount' => $discount,
        ]);

        return $discount;
    }

    /**
     * Returns the eligible discount for display (does not mutate).
     */
    public function preview(Payment $payment, ?Carbon $settlementDate = null): float
    {
        return (float) $this->terms->computeEarlyDiscount($payment, $settlementDate);
    }
}
