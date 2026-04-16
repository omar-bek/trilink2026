<?php

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

class RfqPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isGovernment()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Open RFQs are visible to everyone (marketplace). Non-open RFQs
     * are only visible to the company that created them.
     */
    public function view(User $user, Rfq $rfq): bool
    {
        return $rfq->isOpen() || $user->company_id === $rfq->company_id;
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Only the owning company can update/close/cancel their RFQ.
     */
    public function update(User $user, Rfq $rfq): bool
    {
        return $user->company_id === $rfq->company_id;
    }

    public function delete(User $user, Rfq $rfq): bool
    {
        return $user->company_id === $rfq->company_id;
    }

    public function cancel(User $user, Rfq $rfq): bool
    {
        return $user->company_id === $rfq->company_id;
    }

    /**
     * Can this user's company submit a bid on this RFQ?
     *
     * Why: the FormRequest::authorize() used to accept any authenticated user.
     * An authenticated supplier from Company X could POST to /rfqs/{id}/bids
     * for an RFQ owned by Company Y by manipulating the route ID. Business
     * rules still blocked it at the service layer, but authorization belongs
     * here so it surfaces as 403 (not 422) and is uniformly testable.
     *
     * Gate-level checks only — finer-grained rules (sanctions, exclusive
     * supplier, duplicate bid) stay in BidService::create() so their error
     * messages can reach the UI with context.
     */
    public function submitBid(User $user, Rfq $rfq): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        if (! $rfq->isOpen()) {
            return false;
        }

        return $user->company_id !== $rfq->company_id;
    }
}
