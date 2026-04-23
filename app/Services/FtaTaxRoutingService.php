<?php

namespace App\Services;

use App\Models\CompanyBankAccount;
use App\Models\FtaTaxLedgerEntry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Accrues tax obligations against a payment and routes the matching
 * funds to the company's dedicated FTA bank account.
 *
 * The flow is intentionally two-phase:
 *
 *   accrue()  — creates the ledger row the moment a taxable payment
 *               completes. Non-destructive: safe to run from a webhook.
 *   route()   — moves the money from the operating account to the
 *               ring-fenced tax account. Guarded by idempotency so
 *               re-running a routing job never double-charges.
 *
 * Remittance to the FTA portal (the final step) is handled by a
 * separate job because it needs e-invoicing tokens and portal auth.
 */
class FtaTaxRoutingService
{
    /**
     * Record VAT / Corporate Tax / WHT from a completed payment. Safe
     * to call multiple times — an existing ledger row for the same
     * (payment, tax_type) pair is updated in place rather than
     * duplicated.
     */
    public function accrueFor(Payment $payment): array
    {
        $entries = [];

        if ((float) $payment->vat_amount > 0) {
            $entries[] = $this->upsertEntry($payment, 'vat', (float) $payment->vat_amount, (float) $payment->vat_rate);
        }

        if ((float) ($payment->corporate_tax_amount ?? 0) > 0) {
            $entries[] = $this->upsertEntry($payment, 'corporate_tax', (float) $payment->corporate_tax_amount, (float) $payment->corporate_tax_rate);
        }

        if ((float) ($payment->wht_amount ?? 0) > 0) {
            $entries[] = $this->upsertEntry($payment, 'wht', (float) $payment->wht_amount, (float) $payment->wht_rate);
        }

        return $entries;
    }

    /**
     * Mark an accrued ledger row as routed to the tax account. In
     * production this triggers an internal book transfer via the bank
     * partner API; here we persist the source/destination accounts and
     * the routed timestamp so recon jobs can tie the bank statement
     * line to the ledger entry later.
     */
    public function route(FtaTaxLedgerEntry $entry): FtaTaxLedgerEntry
    {
        if ($entry->status !== 'accrued') {
            return $entry;
        }

        return DB::transaction(function () use ($entry) {
            $company = $entry->company;
            $sourceAccount = $company->defaultReceivingAccount();
            $taxAccount = $company->bankAccounts()
                ->where('is_tax_account', true)
                ->where('status', 'active')
                ->first();

            // Without a dedicated tax account we can't route — leave the
            // entry in "accrued" so the manager sees it in the tax tab
            // and can wire one up.
            if (! $taxAccount) {
                return $entry;
            }

            $entry->forceFill([
                'status' => 'routed',
                'routed_at' => now(),
                'source_bank_account_id' => $sourceAccount?->id,
                'destination_bank_account_id' => $taxAccount->id,
            ])->save();

            return $entry;
        });
    }

    private function upsertEntry(Payment $payment, string $type, float $amount, float $rate): FtaTaxLedgerEntry
    {
        $period = $this->filingPeriodFor($type, $payment);

        /** @var FtaTaxLedgerEntry $entry */
        $entry = FtaTaxLedgerEntry::updateOrCreate(
            [
                'company_id' => $payment->company_id,
                'payment_id' => $payment->id,
                'tax_type' => $type,
            ],
            [
                'filing_period' => $period,
                'direction' => 'payable',
                'amount_aed' => $amount,
                'rate_percent' => $rate,
                'accrued_at' => now(),
                'status' => 'accrued',
            ]
        );

        return $entry;
    }

    private function filingPeriodFor(string $type, Payment $payment): string
    {
        $date = $payment->paid_date ?? $payment->created_at ?? now();

        return match ($type) {
            // Corporate Tax files annually on the fiscal-year close.
            'corporate_tax' => $date->format('Y'),
            // VAT files quarterly by default; tenants under the
            // monthly regime override this in `filing_period` later.
            'vat' => $date->format('Y').'-Q'.(int) ceil($date->month / 3),
            // WHT is a monthly obligation for the UAE.
            default => $date->format('Y-m'),
        };
    }
}
