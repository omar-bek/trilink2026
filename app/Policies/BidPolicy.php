<?php

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

class BidPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A bid is visible to: (a) the supplier company that submitted it,
     * (b) the buyer company that posted the parent RFQ.
     */
    public function view(User $user, Bid $bid): bool
    {
        return $user->company_id === $bid->company_id
            || $user->company_id === $bid->rfq?->company_id;
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    /**
     * Only the supplier company that submitted the bid can update it.
     */
    public function update(User $user, Bid $bid): bool
    {
        return $user->company_id === $bid->company_id;
    }

    public function delete(User $user, Bid $bid): bool
    {
        return $user->company_id === $bid->company_id;
    }

    /**
     * Only the buyer (RFQ owner) can accept or reject a bid.
     */
    public function accept(User $user, Bid $bid): bool
    {
        return $user->company_id === $bid->rfq?->company_id;
    }
}
