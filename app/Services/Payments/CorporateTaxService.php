<?php

namespace App\Services\Payments;

use App\Models\Company;
use App\Models\Payment;

/**
 * UAE Corporate Tax — Federal Decree-Law 47/2022. Applies from 1 June 2023
 * at 9% on taxable income over AED 375,000. Free-zone entities can remain
 * on 0% if they meet the Qualifying Free Zone Person conditions and keep
 * their income outside the "non-qualifying" buckets.
 *
 * This service computes the CT exposure on a single Payment so the
 * dashboards can project quarterly liabilities. It does NOT deduct from
 * the payment — CT is declared and settled on the annual return, not at
 * the transaction level. But knowing each payment's contribution lets
 * finance forecast the CT due and reconcile to the accounting P&L.
 */
class CorporateTaxService
{
    public const RATE_DEFAULT = 9.0;

    public const RATE_QFZP = 0.0;

    public const STANDARD_THRESHOLD_AED = 375000;

    public function apply(Payment $payment): void
    {
        $contract = $payment->contract;
        $applicable = (bool) ($contract?->corporate_tax_applicable ?? false);

        if (! $applicable) {
            $payment->corporate_tax_applicable = false;
            $payment->corporate_tax_rate = 0;
            $payment->corporate_tax_amount = 0;
            return;
        }

        $supplier = $payment->recipient_company_id
            ? Company::find($payment->recipient_company_id)
            : null;

        $rate = $this->rateFor($supplier);
        $payment->corporate_tax_applicable = true;
        $payment->corporate_tax_rate = $rate;

        // CT is applied to taxable INCOME, not gross receipts. At the
        // payment level we approximate by applying the rate to amount
        // minus wht_amount (already-foreign-taxed) minus any corporate
        // tax already allocated for this payment upstream.
        $base = max(0.0, (float) $payment->amount - (float) ($payment->wht_amount ?? 0));
        $payment->corporate_tax_amount = $rate > 0
            ? round($base * ($rate / 100.0), 2)
            : 0;
    }

    private function rateFor(?Company $supplier): float
    {
        if (! $supplier) {
            return self::RATE_DEFAULT;
        }

        // Free zone recognition. Ideally the Company model carries a
        // is_free_zone + qfzp_status pair; until those exist, fall back
        // to country + name heuristics so free-zone tenants don't
        // over-accrue CT. Conservative default: standard 9%.
        $freeZone = (bool) ($supplier->is_free_zone ?? false);
        $qfzp = (bool) ($supplier->qfzp_status ?? false);

        if ($freeZone && $qfzp) {
            return self::RATE_QFZP;
        }

        return self::RATE_DEFAULT;
    }
}
