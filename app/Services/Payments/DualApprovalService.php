<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentApproval;
use App\Models\User;
use App\Services\Payments\FxLockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dual approval gate. A payment whose AED-equivalent amount exceeds the
 * contract's dual_approval_threshold_aed (or the platform default, AED
 * 500k) must be approved by a SECOND user before it can be processed.
 *
 *   - First call records a 'primary' approval and flips
 *     requires_dual_approval = true, leaving status = PENDING_APPROVAL.
 *   - Second call (different user, payment.approve permission) records
 *     a 'secondary' approval and promotes status to APPROVED.
 *   - Same user cannot double-sign (unique index on
 *     payment_approvals.(payment_id, approver_id, role)).
 */
class DualApprovalService
{
    public const DEFAULT_THRESHOLD_AED = 500000;

    public function __construct(private readonly FxLockService $fx) {}

    public function requiresDualApproval(Payment $payment): bool
    {
        $threshold = (float) ($payment->contract?->dual_approval_threshold_aed
            ?? config('payments.dual_approval_threshold_aed', self::DEFAULT_THRESHOLD_AED));

        if ($threshold <= 0) {
            return false;
        }

        // Make sure we're comparing in AED. If the Payment hasn't been
        // fx-locked yet, lock against TODAY so this query is accurate.
        if (! $payment->fx_locked_at) {
            $this->fx->lock($payment);
        }

        $aedEquivalent = (float) ($payment->amount_in_base ?? $payment->amount);

        return $aedEquivalent >= $threshold;
    }

    public function recordPrimary(Payment $payment, User $approver, ?string $notes = null): Payment
    {
        if ($approver->id === (int) $payment->second_approver_id) {
            throw new RuntimeException(__('payments.dual_approval_same_user'));
        }

        return DB::transaction(function () use ($payment, $approver, $notes) {
            PaymentApproval::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'approver_id' => $approver->id,
                    'role' => PaymentApproval::ROLE_PRIMARY,
                ],
                [
                    'action' => PaymentApproval::ACTION_APPROVED,
                    'notes' => $notes,
                    'amount_snapshot' => $payment->amount,
                    'currency_snapshot' => $payment->currency,
                    'ip_address' => request()?->ip(),
                    'user_agent' => substr((string) request()?->userAgent(), 0, 500),
                ]
            );

            $payment->update([
                'requires_dual_approval' => true,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $payment->fresh();
        });
    }

    public function recordSecondary(Payment $payment, User $approver, ?string $notes = null): Payment
    {
        $primaryId = (int) ($payment->approved_by ?? 0);
        if ($primaryId === 0) {
            throw new RuntimeException(__('payments.dual_approval_missing_primary'));
        }
        if ($approver->id === $primaryId) {
            throw new RuntimeException(__('payments.dual_approval_same_user'));
        }

        return DB::transaction(function () use ($payment, $approver, $notes) {
            PaymentApproval::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'approver_id' => $approver->id,
                    'role' => PaymentApproval::ROLE_SECONDARY,
                ],
                [
                    'action' => PaymentApproval::ACTION_APPROVED,
                    'notes' => $notes,
                    'amount_snapshot' => $payment->amount,
                    'currency_snapshot' => $payment->currency,
                    'ip_address' => request()?->ip(),
                    'user_agent' => substr((string) request()?->userAgent(), 0, 500),
                ]
            );

            $payment->update([
                'second_approver_id' => $approver->id,
                'second_approved_at' => now(),
                'status' => PaymentStatus::APPROVED->value,
            ]);

            return $payment->fresh();
        });
    }
}
