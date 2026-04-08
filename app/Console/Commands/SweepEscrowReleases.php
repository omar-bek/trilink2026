<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Services\Escrow\BankPartnerException;
use App\Services\EscrowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 / Sprint 13 / task 3.11 — sweeper that runs every 10 minutes and
 * releases any escrow milestone whose `release_condition` should already
 * have fired but didn't. The reason something can be missed:
 *
 *   1. ContractSigned listener was on the queue and bombed.
 *   2. ShipmentDelivered listener was on the queue and bombed.
 *   3. The condition is `manual` but the buyer flagged a payment as
 *      "ready to release" and the listener has nothing to wake on.
 *   4. The condition is `on_inspection_pass` and there is no listener
 *      yet (inspection workflow is Phase 4) — the cron is the only path.
 *
 * The sweeper is idempotent: it only releases payments that are still
 * unpaid, so re-running it is safe even if a previous tick partially
 * succeeded.
 */
class SweepEscrowReleases extends Command
{
    protected $signature = 'escrow:sweep';
    protected $description = 'Auto-release escrow milestones whose release conditions are met (Phase 3 / task 3.11).';

    public function handle(EscrowService $service): int
    {
        $accounts = EscrowAccount::query()
            ->where('status', EscrowAccount::STATUS_ACTIVE)
            ->with(['contract.payments', 'contract.shipments'])
            ->get();

        $released = 0;
        foreach ($accounts as $account) {
            $contract = $account->contract;
            if (!$contract) {
                continue;
            }

            // Each condition's gate is checked here so the sweeper has full
            // ownership of the "should this fire?" logic. Listeners only
            // serve to make the happy path real-time; the sweeper is the
            // safety net.
            if ($this->contractIsFullySigned($contract)) {
                $released += $this->releasePayments($service, $account, $contract, 'on_signature', EscrowRelease::TRIGGER_CRON);
            }

            if ($this->contractHasDeliveredShipment($contract)) {
                $released += $this->releasePayments($service, $account, $contract, 'on_delivery', EscrowRelease::TRIGGER_CRON);
            }

            if ($this->contractHasInspectionPass($contract)) {
                $released += $this->releasePayments($service, $account, $contract, 'on_inspection_pass', EscrowRelease::TRIGGER_CRON);
            }
        }

        $this->info("Sweep complete: {$released} releases dispatched.");
        return self::SUCCESS;
    }

    private function releasePayments(EscrowService $service, EscrowAccount $account, Contract $contract, string $condition, string $trigger): int
    {
        $payments = $service->pendingPaymentsFor($contract, $condition);
        $count = 0;

        foreach ($payments as $payment) {
            // Skip if balance is too low — wait for the buyer to top up.
            if ($account->fresh()->availableBalance() + 0.01 < (float) $payment->total_amount) {
                continue;
            }

            try {
                $service->release(
                    account: $account,
                    amount: (float) $payment->total_amount,
                    currency: $payment->currency ?? $contract->currency ?? 'AED',
                    milestone: $payment->milestone,
                    payment: $payment,
                    trigger: $trigger,
                    notes: 'Released by escrow:sweep cron',
                );
                $count++;
            } catch (BankPartnerException $e) {
                Log::warning('Sweeper release failed', [
                    'contract_id' => $contract->id,
                    'payment_id'  => $payment->id,
                    'condition'   => $condition,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    private function contractIsFullySigned(Contract $contract): bool
    {
        return $contract->allPartiesHaveSigned();
    }

    private function contractHasDeliveredShipment(Contract $contract): bool
    {
        return $contract->shipments
            ->contains(fn ($s) => ($s->status?->value ?? $s->status) === 'delivered');
    }

    /**
     * Inspection workflow lands in Phase 4. Until then we treat
     * `inspection_status = 'passed'` on any shipment as a pass — the
     * column already exists on the shipments table from Phase 0.
     */
    private function contractHasInspectionPass(Contract $contract): bool
    {
        return $contract->shipments
            ->contains(fn ($s) => $s->inspection_status === 'passed');
    }
}
