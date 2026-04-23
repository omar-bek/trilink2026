<?php

namespace App\Services\Payments;

use App\Models\Company;
use App\Models\Payment;

/**
 * Withholding tax (WHT) — the UAE itself does not impose domestic WHT on
 * most B2B service/goods payments, but cross-border outbound payments to
 * non-treaty jurisdictions frequently carry WHT under the foreign
 * country's rules (e.g. 5-15% on royalties, dividends, technical fees).
 *
 * Logic here:
 *   - If the recipient is outside UAE and the contract sets a
 *     `default_wht_rate`, stamp that rate on the Payment and compute
 *     wht_amount = amount × rate / 100.
 *   - The amount the supplier actually receives is amount − wht_amount.
 *     We do NOT mutate payments.amount (legal/contractual obligation
 *     stays full), we just record the WHT that must be remitted to the
 *     foreign tax authority.
 */
class WhtService
{
    public function apply(Payment $payment): void
    {
        $contract = $payment->contract;
        $rate = $payment->wht_rate !== null
            ? (float) $payment->wht_rate
            : (float) ($contract?->default_wht_rate ?? 0);

        if ($rate <= 0) {
            $payment->wht_rate = 0;
            $payment->wht_amount = 0;
            return;
        }

        if (! $this->recipientIsCrossBorder($payment)) {
            // Domestic UAE recipient: no WHT unless explicitly set.
            if ($payment->wht_rate === null) {
                $payment->wht_rate = 0;
                $payment->wht_amount = 0;
                return;
            }
        }

        $rate = min($rate, 30.0); // sanity cap — no jurisdiction imposes > 30%
        $amount = (float) $payment->amount;
        $wht = round($amount * ($rate / 100.0), 2);

        $payment->wht_rate = $rate;
        $payment->wht_amount = $wht;
    }

    private function recipientIsCrossBorder(Payment $payment): bool
    {
        $recipient = $payment->recipient_company_id
            ? Company::find($payment->recipient_company_id)
            : null;

        if (! $recipient) {
            return false;
        }

        $country = strtoupper((string) ($recipient->country ?? 'AE'));

        return $country !== 'AE';
    }
}
