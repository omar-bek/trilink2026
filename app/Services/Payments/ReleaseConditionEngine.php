<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Services\EscrowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drives the escrow auto-release pipeline from the schedule's
 * release_condition field.
 *
 * Supported conditions:
 *   - on_signature     : fires when all contract parties have signed.
 *   - on_delivery      : fires when the shipment is marked delivered.
 *   - on_inspection_pass: fires when an inspection record passes.
 *   - retention_period_elapsed: fires when contract.retention_release_date
 *                         is past.
 *   - manual           : never auto-fires; finance releases by hand.
 *
 * Idempotent: a Payment that is already COMPLETED / has escrow_release_id
 * set is skipped. Errors from the bank partner are logged but never
 * bubble — the caller (listener / cron) should not be rolled back by a
 * transient bank blip.
 */
class ReleaseConditionEngine
{
    public function __construct(private readonly EscrowService $escrow) {}

    public function onEvent(Contract $contract, string $condition): int
    {
        $contract->loadMissing('escrowAccount');
        $account = $contract->escrowAccount;
        if (! $account) {
            return 0;
        }

        $schedule = $contract->payment_schedule ?? [];
        if ($schedule === []) {
            return 0;
        }

        $released = 0;
        foreach ($schedule as $row) {
            if (($row['release_condition'] ?? '') !== $condition) {
                continue;
            }

            $milestone = (string) ($row['milestone'] ?? '');
            $payment = Payment::query()
                ->where('contract_id', $contract->id)
                ->where('milestone', $milestone)
                ->whereNotIn('status', [
                    PaymentStatus::COMPLETED->value,
                    PaymentStatus::REFUNDED->value,
                    PaymentStatus::CANCELLED->value,
                ])
                ->whereNull('escrow_release_id')
                ->first();

            if (! $payment) {
                continue;
            }

            try {
                DB::transaction(function () use ($account, $payment, $milestone, $condition) {
                    $this->escrow->release(
                        account: $account,
                        amount: (float) $payment->amount,
                        currency: (string) ($payment->currency ?? $account->currency ?? 'AED'),
                        milestone: $milestone,
                        payment: $payment,
                        trigger: 'auto_'.$condition,
                    );
                });
                $released++;
            } catch (Throwable $e) {
                Log::warning('release-condition-engine skipped payment', [
                    'payment_id' => $payment->id,
                    'condition' => $condition,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return $released;
    }
}
