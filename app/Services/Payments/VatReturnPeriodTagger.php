<?php

namespace App\Services\Payments;

use App\Models\TaxInvoice;
use Carbon\CarbonInterface;

/**
 * Tags each TaxInvoice with its VAT return period (YYYY-Q#) based on the
 * FTA's quarterly filing cadence. Large filers (revenue > AED 150M) file
 * monthly so the platform-level default is quarterly; monthly filing is
 * opt-in per tenant in config.
 */
class VatReturnPeriodTagger
{
    public function tagForDate(TaxInvoice $invoice, ?CarbonInterface $date = null, string $cadence = 'quarterly'): void
    {
        $date ??= $invoice->issue_date ?? now();
        $year = (int) $date->format('Y');

        $period = $cadence === 'monthly'
            ? sprintf('%d-%02d', $year, (int) $date->format('m'))
            : sprintf('%d-Q%d', $year, (int) ceil(((int) $date->format('m')) / 3));

        $invoice->update(['vat_return_period' => $period]);
    }
}
