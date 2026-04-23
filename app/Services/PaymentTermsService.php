<?php

namespace App\Services;

use App\Enums\PaymentTerms;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Carbon\Carbon;

/**
 * Computes the financial consequences of commercial payment terms:
 * due dates honouring UAE business calendar, late fees per the UAE
 * Civil Code cap of 12% per annum, and early-payment discounts.
 *
 * All monetary math uses bccomp/bcmul on strings so we don't lose
 * fractional AED fils to float error when computing fees on large
 * contracts.
 */
class PaymentTermsService
{
    public function __construct(private readonly SettlementCalendarService $calendar) {}

    /**
     * Derive a Payment's due_date from its invoice_issued_at and the
     * parent contract's payment_terms. Adjusts forward if the raw due
     * date falls on a UAE holiday or a weekend (Fri/Sat).
     */
    public function computeDueDate(Payment $payment): ?Carbon
    {
        $issued = $payment->invoice_issued_at ? Carbon::parse($payment->invoice_issued_at) : null;
        if (! $issued) {
            return null;
        }

        $terms = PaymentTerms::tryFrom((string) ($payment->contract?->payment_terms ?? 'net_30'))
            ?? PaymentTerms::NET_30;

        if ($terms === PaymentTerms::COD) {
            return $this->calendar->nextBusinessDay($issued->copy());
        }

        $due = $terms->isEndOfMonth()
            ? $issued->copy()->endOfMonth()->addDays($terms->days())
            : $issued->copy()->addDays($terms->days());

        return $this->calendar->nextBusinessDay($due);
    }

    /**
     * Compute late fee as of $asOf date. Uses the contract's
     * `late_fee_annual_rate` (capped at 12% per UAE Civil Code Article
     * 76 & Federal Law 18/1993). Returns 0.00 if not late or no rate.
     */
    public function computeLateFee(Payment $payment, ?CarbonInterface $asOf = null): string
    {
        $asOf ??= now();
        $dueDate = $payment->due_date ? Carbon::parse($payment->due_date) : null;
        if (! $dueDate || $dueDate->greaterThan($asOf)) {
            return '0.00';
        }

        $annualRate = (float) ($payment->contract?->late_fee_annual_rate ?? 0);
        if ($annualRate <= 0) {
            return '0.00';
        }
        $annualRate = min($annualRate, 12.0); // statutory cap

        $daysLate = (int) $dueDate->diffInDays($asOf);
        $dailyRate = $annualRate / 365.0;
        $principal = (string) $payment->amount;

        $fee = bcmul(bcmul($principal, (string) ($dailyRate / 100.0), 8), (string) $daysLate, 8);

        return bcadd('0', $fee, 2);
    }

    /**
     * Compute early-payment discount if the payment would be settled
     * within the contract's discount window. Returns the discount
     * amount (positive) or 0.00.
     */
    public function computeEarlyDiscount(Payment $payment, ?CarbonInterface $settlementDate = null): string
    {
        $settlementDate ??= now();
        $terms = PaymentTerms::tryFrom((string) ($payment->contract?->payment_terms ?? ''));
        if (! $terms) {
            return '0.00';
        }

        $rate = (float) ($payment->contract?->early_discount_rate ?? $terms->earlyDiscountRate() ?? 0);
        $days = (int) ($payment->contract?->early_discount_days ?? $terms->earlyDiscountDays() ?? 0);
        if ($rate <= 0 || $days <= 0) {
            return '0.00';
        }

        $issued = $payment->invoice_issued_at ? Carbon::parse($payment->invoice_issued_at) : null;
        if (! $issued) {
            return '0.00';
        }

        $cutoff = $issued->copy()->addDays($days);
        if ($settlementDate->greaterThan($cutoff)) {
            return '0.00';
        }

        return bcmul((string) $payment->amount, (string) ($rate / 100.0), 2);
    }

    /**
     * Days Sales Outstanding for a supplier — average number of days it
     * takes to get paid. Useful KPI on the supplier dashboard.
     */
    public function dsoForSupplier(int $supplierCompanyId): float
    {
        $rows = Payment::query()
            ->where('recipient_company_id', $supplierCompanyId)
            ->whereNotNull('settled_at')
            ->whereNotNull('invoice_issued_at')
            ->get(['invoice_issued_at', 'settled_at']);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $totalDays = $rows->reduce(fn ($carry, $p) => $carry + Carbon::parse($p->invoice_issued_at)->diffInDays($p->settled_at), 0);

        return round($totalDays / max($rows->count(), 1), 1);
    }

    /**
     * Available credit for a buyer against a supplier — the remaining
     * gap between the contract's credit_limit and unsettled payables.
     * Used to block new payment scheduling when a buyer has maxed out.
     */
    public function availableCredit(int $buyerCompanyId, int $supplierCompanyId): ?float
    {
        $limit = Contract::query()
            ->where('buyer_company_id', $buyerCompanyId)
            ->whereJsonContains('parties', ['company_id' => $supplierCompanyId])
            ->max('credit_limit');

        if (! $limit) {
            return null;
        }

        $outstanding = (float) Payment::query()
            ->where('company_id', $buyerCompanyId)
            ->where('recipient_company_id', $supplierCompanyId)
            ->whereNull('settled_at')
            ->whereNotIn('status', ['rejected', 'cancelled', 'refunded'])
            ->sum('amount');

        return max(0, (float) $limit - $outstanding);
    }
}
