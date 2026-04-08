<?php

namespace App\Listeners;

use App\Events\ShipmentDelivered;
use App\Models\EscrowRelease;
use App\Services\Escrow\BankPartnerException;
use App\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 / Sprint 12 / task 3.7 — when a shipment is delivered, drain
 * any escrow milestone whose release_condition is `on_delivery` against
 * the underlying contract. Each release is recorded with trigger =
 * auto_delivery so the audit trail can attribute it to this event.
 *
 * Runs on the queue (not synchronously) so a webhook-driven status flip
 * doesn't make the bank API call inside the HTTP request.
 */
class ReleaseEscrowOnDelivery implements ShouldQueue
{
    public string $queue = 'escrow';

    public function __construct(private readonly EscrowService $escrowService)
    {
    }

    public function handle(ShipmentDelivered $event): void
    {
        $shipment = $event->shipment->fresh(['contract.escrowAccount', 'contract.payments']);
        $contract = $shipment?->contract;

        if (!$contract || !$contract->escrowAccount || !$contract->escrowAccount->isActive()) {
            return;
        }

        $payments = $this->escrowService->pendingPaymentsFor($contract, 'on_delivery');
        foreach ($payments as $payment) {
            try {
                $this->escrowService->release(
                    account: $contract->escrowAccount,
                    amount: (float) $payment->total_amount,
                    currency: $payment->currency ?? $contract->currency ?? 'AED',
                    milestone: $payment->milestone,
                    payment: $payment,
                    trigger: EscrowRelease::TRIGGER_AUTO_DELIVERY,
                );
            } catch (BankPartnerException $e) {
                // Log and move on — the cron sweeper retries failed
                // releases on its next 10-minute tick.
                Log::warning('Auto-release on delivery failed', [
                    'contract_id' => $contract->id,
                    'payment_id'  => $payment->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
