<?php

namespace App\Listeners;

use App\Events\ContractSigned;
use App\Models\EscrowRelease;
use App\Services\Escrow\BankPartnerException;
use App\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 / Sprint 13 — release the advance milestone the moment all
 * parties have signed the contract. Only fires for contracts that already
 * have an active escrow account (i.e. the buyer pre-funded before
 * signing) — otherwise there's nothing to release yet.
 *
 * Runs on the escrow queue so the contract sign HTTP request returns
 * immediately and the bank call happens out-of-band.
 */
class ReleaseEscrowOnSignature implements ShouldQueue
{
    public string $queue = 'escrow';

    public function __construct(private readonly EscrowService $escrowService) {}

    public function handle(ContractSigned $event): void
    {
        $contract = $event->contract->fresh(['escrowAccount', 'payments']);

        if (! $contract || ! $contract->escrowAccount || ! $contract->escrowAccount->isActive()) {
            return;
        }

        $payments = $this->escrowService->pendingPaymentsFor($contract, 'on_signature');
        foreach ($payments as $payment) {
            try {
                $this->escrowService->release(
                    account: $contract->escrowAccount,
                    amount: (float) $payment->total_amount,
                    currency: $payment->currency ?? $contract->currency ?? 'AED',
                    milestone: $payment->milestone,
                    payment: $payment,
                    trigger: EscrowRelease::TRIGGER_AUTO_SIGNATURE,
                );
            } catch (BankPartnerException $e) {
                Log::warning('Auto-release on signature failed', [
                    'contract_id' => $contract->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
