<?php

namespace App\Services;

use App\Enums\BankGuaranteeType;
use App\Models\Contract;
use App\Notifications\EscrowEventNotification;
use App\Services\Escrow\BankPartnerException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Runs the book-keeping that has to happen the moment a bid moves from
 * ACCEPTED / negotiation-accepted into a signed contract:
 *
 *   1. If the contract value ≥ AED 50k (configurable), automatically
 *      open an escrow account. The buyer still has to deposit, but the
 *      account row exists, the bank reference is issued, and the
 *      contract show page renders the escrow panel out of the box.
 *
 *   2. If the RFQ / contract policy requires a Performance Bond (the
 *      default assumption for contracts ≥ AED 250k), raise an advisory
 *      BG request so finance can chase the supplier's bank.
 *
 * Failures here must NEVER roll back the accept — the contract already
 * exists, and a transient bank-partner error shouldn't unwind the legal
 * acceptance. We log and carry on; finance can retry from the contract
 * page.
 */
class PostAcceptHookService
{
    public function __construct(
        private readonly EscrowService $escrow,
    ) {}

    public function run(Contract $contract): void
    {
        $this->maybeActivateEscrow($contract);
        $this->maybeAdvisePerformanceBond($contract);
    }

    private function maybeActivateEscrow(Contract $contract): void
    {
        $threshold = (float) config('negotiation.escrow_auto_threshold_aed', 50000);
        $total = (float) ($contract->total_amount ?? $contract->contract_value ?? 0);
        $currency = strtoupper((string) ($contract->currency ?? 'AED'));

        // Only AED contracts trigger the auto-open — for other currencies
        // the threshold needs FX, which finance handles manually.
        if ($currency !== 'AED' || $total < $threshold) {
            return;
        }

        try {
            $this->escrow->activate($contract);
        } catch (BankPartnerException|Throwable $e) {
            Log::warning('post-accept escrow activation skipped', [
                'contract_id' => $contract->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function maybeAdvisePerformanceBond(Contract $contract): void
    {
        $threshold = (float) config('negotiation.bg_advisory_threshold_aed', 250000);
        $total = (float) ($contract->total_amount ?? $contract->contract_value ?? 0);
        $currency = strtoupper((string) ($contract->currency ?? 'AED'));

        if ($currency !== 'AED' || $total < $threshold) {
            return;
        }

        // Thin advisory: emit an EscrowEventNotification variant so finance
        // gets pinged without coupling this service to a BG-specific
        // notification class. The dedicated BG advisory channel gets wired
        // up in Sprint 14 — for now a Log entry + an audit-trailed ping is
        // enough to avoid silent drop on the floor.
        Log::info('post-accept BG advisory raised', [
            'contract_id' => $contract->id,
            'amount' => $total,
            'suggested_bg_type' => BankGuaranteeType::PERFORMANCE_BOND->value,
            'suggested_percentage' => BankGuaranteeType::PERFORMANCE_BOND->defaultPercentage(),
        ]);

        $partyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->all();

        if ($partyIds === []) {
            return;
        }

        $recipients = \App\Models\User::whereIn('company_id', $partyIds)
            ->when(method_exists(\App\Models\User::class, 'scopeActive'), fn ($q) => $q->active())
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EscrowEventNotification(
            contractId: $contract->id,
            action: 'bg_advisory',
            amount: $total,
            currency: $currency,
        ));
    }
}
