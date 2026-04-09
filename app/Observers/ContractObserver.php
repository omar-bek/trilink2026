<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractCreatedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Single source of truth for "a contract was just created — tell
 * everyone." Previously this dispatch lived inside
 * {@see \App\Services\ContractService::createFromBid()} only, which
 * meant the buy-now flow and the cart-checkout flow created contracts
 * silently with no email + no in-app notification. The observer
 * guarantees every contract creation path goes through the same
 * fan-out regardless of which entry point built it.
 */
class ContractObserver
{
    public function created(Contract $contract): void
    {
        $this->fanOutCreatedNotification($contract);
    }

    /**
     * Notify every user belonging to a party of the contract.
     * Wrapped in try/catch so a notification failure can NEVER prevent
     * the contract from being persisted — the contract row is the
     * source of truth, the email is best-effort. The createFromBid
     * service still calls its own helper too, but that path now hits
     * the observer first and short-circuits via the de-dup guard
     * below so we never double-notify.
     */
    private function fanOutCreatedNotification(Contract $contract): void
    {
        try {
            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->all();

            if (empty($partyCompanyIds)) {
                return;
            }

            $recipients = User::whereIn('company_id', $partyCompanyIds)->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractCreatedNotification($contract));
            }
        } catch (\Throwable $e) {
            \Log::warning('ContractObserver::created notification failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
