<?php

namespace App\Services\Payments;

use App\Enums\PaymentMilestone;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentTermsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns the theoretical late fee (from PaymentTermsService::computeLateFee)
 * into a real, claimable Payment row of milestone=LATE_FEE linked to the
 * parent payment.
 *
 * Called daily by a cron sweeper. Idempotent per (parent_payment_id,
 * as_of_month) so re-runs never double-charge.
 */
class LateFeeAccrualService
{
    public function __construct(private readonly PaymentTermsService $terms) {}

    public function accrueFor(Payment $payment, ?Carbon $asOf = null): ?Payment
    {
        $asOf ??= now();

        $statusValue = $payment->status instanceof \BackedEnum
            ? $payment->status->value
            : (string) $payment->status;

        // Only past-due, unsettled, non-late-fee payments accrue.
        if ($payment->is_late_fee_accrual) {
            return null;
        }
        if (! in_array($statusValue, [PaymentStatus::APPROVED->value, PaymentStatus::PENDING_APPROVAL->value], true)) {
            return null;
        }
        if (! $payment->due_date || Carbon::parse($payment->due_date)->greaterThan($asOf)) {
            return null;
        }

        $fee = (float) $this->terms->computeLateFee($payment, $asOf);
        if ($fee <= 0) {
            return null;
        }

        // Idempotency: one accrual row per (parent, year-month). Uses
        // month boundaries (DB-agnostic) instead of a driver-specific
        // date-format function so sqlite-in-memory tests + MySQL prod
        // both match the same semantics.
        $monthStart = $asOf->copy()->startOfMonth();
        $monthEnd = $asOf->copy()->endOfMonth();
        $existing = Payment::query()
            ->where('parent_payment_id', $payment->id)
            ->where('milestone', PaymentMilestone::LATE_FEE->value)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($payment, $fee) {
            return Payment::create([
                'contract_id' => $payment->contract_id,
                'company_id' => $payment->company_id,
                'recipient_company_id' => $payment->recipient_company_id,
                'buyer_id' => $payment->buyer_id,
                'status' => PaymentStatus::PENDING_APPROVAL->value,
                'amount' => $fee,
                'currency' => $payment->currency ?? 'AED',
                'milestone' => PaymentMilestone::LATE_FEE->value,
                'is_late_fee_accrual' => true,
                'parent_payment_id' => $payment->id,
                'vat_rate' => 0, // statutory interest — not a VATable supply
            ]);
        });
    }
}
