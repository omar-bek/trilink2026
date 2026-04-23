<?php

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

/**
 * Gatekeeper for the negotiation room (bid-level). Participants are either
 * the buyer company (owns the RFQ) or the supplier company (owns the bid).
 * Admins bypass all checks via before().
 */
class NegotiationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * See the negotiation room + timeline.
     */
    public function view(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    /**
     * Post a free-text message.
     */
    public function message(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    /**
     * Open a new counter-offer round. Either side can counter — same-side
     * collision is blocked by the service layer (sender_side check).
     */
    public function counter(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    /**
     * Accept the currently open round. Either buyer or supplier can accept
     * (whoever is on the opposite side of the sender).
     */
    public function accept(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    /**
     * Reject the currently open round. Same eligibility as accept.
     */
    public function reject(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    /**
     * End the negotiation (buyer → REJECTED, supplier → WITHDRAWN).
     */
    public function end(User $user, Bid $bid): bool
    {
        return $this->isParticipant($user, $bid);
    }

    private function isParticipant(User $user, Bid $bid): bool
    {
        if (! $user->company_id) {
            return false;
        }

        return $user->company_id === $bid->company_id
            || $user->company_id === $bid->rfq?->company_id;
    }
}
