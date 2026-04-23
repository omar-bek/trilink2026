<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retention (الاحتجاز) — standard UAE construction / manufacturing
 * practice of withholding 5-10% of every milestone payment until the
 * warranty period ends. Protects the buyer against latent defects.
 *
 * This service centralises three operations:
 *   - skim()          : called as each Payment is approved. Computes the
 *                       retention amount for that milestone, updates
 *                       the running total on the Contract, and stamps
 *                       early_discount_amount on the Payment so the
 *                       finance dashboard can show "paid X, retained Y".
 *   - releaseAll()    : called once the retention_release_date is hit
 *                       (cron or manual). Creates one "retention_release"
 *                       Payment for the cumulative held amount.
 *   - pendingRelease(): read-only lookup used by the cron sweeper and
 *                       the buyer dashboard's "due retention" card.
 */
class RetentionService
{
    /**
     * Compute and record the retention slice for a freshly-approved
     * Payment. Non-destructive — leaves the payment amount intact but
     * tracks the portion that should NOT actually leave the buyer's
     * account until warranty end. `retention_amount` on the contract
     * is the running total; we add on every approval.
     *
     * Returns the retention amount applied (0.00 if the contract has no
     * retention_percentage or if this payment is itself a retention
     * release).
     */
    public function skim(Payment $payment): string
    {
        $contract = $payment->contract;
        if (! $contract || ! $contract->retention_percentage) {
            return '0.00';
        }

        // Don't skim retention releases themselves.
        if ($payment->milestone === 'retention_release') {
            return '0.00';
        }

        $pct = (float) $contract->retention_percentage;
        if ($pct <= 0) {
            return '0.00';
        }

        $held = bcmul((string) $payment->amount, (string) ($pct / 100.0), 2);

        DB::transaction(function () use ($contract, $held) {
            $current = (string) ($contract->retention_amount ?? '0.00');
            $contract->update([
                'retention_amount' => bcadd($current, $held, 2),
            ]);
        });

        return $held;
    }

    /**
     * Release the accumulated retention as a single closing Payment. The
     * new row is created in PENDING_APPROVAL so the buyer's finance
     * user still sees and approves the outflow — we never auto-move
     * money without a human in the loop.
     *
     * Idempotent: returns null on the second call for the same contract.
     */
    public function releaseAll(Contract $contract): ?Payment
    {
        if ($contract->retention_released_at !== null) {
            return null;
        }
        $amount = (float) ($contract->retention_amount ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $supplierId = collect($contract->parties ?? [])
            ->firstWhere('role', 'supplier')['company_id'] ?? null;
        if (! $supplierId) {
            throw new RuntimeException('Contract has no supplier party; cannot release retention.');
        }

        return DB::transaction(function () use ($contract, $amount, $supplierId) {
            $payment = Payment::create([
                'contract_id' => $contract->id,
                'company_id' => $contract->buyer_company_id,
                'recipient_company_id' => $supplierId,
                'buyer_id' => $contract->purchaseRequest?->buyer_id
                    ?? \App\Models\User::where('company_id', $contract->buyer_company_id)->value('id'),
                'status' => PaymentStatus::PENDING_APPROVAL->value,
                'amount' => $amount,
                'currency' => $contract->currency ?? 'AED',
                'milestone' => 'retention_release',
                'vat_rate' => 0, // retention release is not a VATable supply
            ]);

            $contract->update([
                'retention_released_at' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * Contracts whose retention_release_date has passed but haven't
     * been released yet. Drives the buyer dashboard card and the cron
     * sweeper.
     *
     * @return \Illuminate\Support\Collection<int, Contract>
     */
    public function pendingRelease(?Carbon $asOf = null): \Illuminate\Support\Collection
    {
        $asOf ??= now();

        return Contract::query()
            ->whereNotNull('retention_percentage')
            ->whereNotNull('retention_release_date')
            ->whereNull('retention_released_at')
            ->where('retention_release_date', '<=', $asOf)
            ->get();
    }
}
