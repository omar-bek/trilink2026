<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Structured negotiation flow on top of the existing negotiation_messages
 * table.
 *
 * The flow:
 *   1. Buyer or supplier opens a counter_offer (round 1).
 *   2. The other side responds: accept, reject, or counter (round 2).
 *   3. Continues until accepted or rejected. The bid's price/terms get
 *      updated to whatever the accepted offer specifies, and the bid moves
 *      to UNDER_REVIEW so the buyer can finalise the contract.
 *
 * Plain text chat messages are still allowed via postText() and don't
 * affect the round counter.
 */
class NegotiationService
{
    /**
     * Post a free-text chat message in the negotiation room. Doesn't change
     * any state — pure conversation.
     */
    public function postText(Bid $bid, User $sender, string $body): NegotiationMessage
    {
        return NegotiationMessage::create([
            'bid_id' => $bid->id,
            'sender_id' => $sender->id,
            'sender_side' => $this->resolveSide($bid, $sender),
            'kind' => NegotiationMessage::KIND_TEXT,
            'body' => $body,
            'round_status' => NegotiationMessage::ROUND_OPEN,
        ]);
    }

    /**
     * Open a new round with a counter-offer. Auto-increments the round
     * number and marks any previous open round as "countered".
     */
    public function openCounterOffer(Bid $bid, User $sender, array $offer, ?string $reason = null): NegotiationMessage
    {
        return DB::transaction(function () use ($bid, $sender, $offer, $reason) {
            // Close the previous open round (if any).
            NegotiationMessage::where('bid_id', $bid->id)
                ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
                ->where('round_status', NegotiationMessage::ROUND_OPEN)
                ->update(['round_status' => NegotiationMessage::ROUND_COUNTERED]);

            $nextRound = (int) NegotiationMessage::where('bid_id', $bid->id)
                ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
                ->max('round_number') + 1;

            return NegotiationMessage::create([
                'bid_id' => $bid->id,
                'sender_id' => $sender->id,
                'sender_side' => $this->resolveSide($bid, $sender),
                'kind' => NegotiationMessage::KIND_COUNTER_OFFER,
                'body' => $reason,
                'offer' => $offer,
                'round_number' => $nextRound,
                'round_status' => NegotiationMessage::ROUND_OPEN,
            ]);
        });
    }

    /**
     * Accept the latest open round. Closes the round, applies the offer to
     * the bid, and bumps the bid into UNDER_REVIEW so the buyer can finalise.
     */
    public function acceptOffer(Bid $bid, User $sender): ?NegotiationMessage
    {
        return DB::transaction(function () use ($bid, $sender) {
            $latest = $this->latestOpenRound($bid);
            if (! $latest) {
                return null;
            }

            $latest->update([
                'round_status' => NegotiationMessage::ROUND_ACCEPTED,
            ]);

            // Apply the accepted offer back to the bid so the contract
            // pipeline picks up the negotiated values, not the original.
            $offer = $latest->offer ?? [];
            $bid->update(array_filter([
                'price' => $offer['amount'] ?? null,
                'currency' => $offer['currency'] ?? null,
                'delivery_time_days' => $offer['delivery_days'] ?? null,
                'payment_terms' => $offer['payment_terms'] ?? null,
                'status' => BidStatus::UNDER_REVIEW,
            ], fn ($v) => $v !== null));

            $this->postText($bid, $sender, '✓ Offer accepted at round '.$latest->round_number);

            return $latest->fresh();
        });
    }

    /**
     * Reject the latest open round without making a new counter. The bid
     * stays in its current status — the buyer/supplier can decide what to
     * do next via the regular Bid actions.
     */
    public function rejectOffer(Bid $bid, User $sender, ?string $reason = null): ?NegotiationMessage
    {
        $latest = $this->latestOpenRound($bid);
        if (! $latest) {
            return null;
        }

        $latest->update(['round_status' => NegotiationMessage::ROUND_REJECTED]);
        $this->postText($bid, $sender, '✗ Offer rejected at round '.$latest->round_number.($reason ? ': '.$reason : ''));

        return $latest->fresh();
    }

    /**
     * Full negotiation timeline (text + offers) ordered by creation time.
     * Used by the bid show page.
     */
    public function timeline(Bid $bid)
    {
        return NegotiationMessage::with('sender')
            ->where('bid_id', $bid->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The newest open counter-offer for a bid, if any.
     */
    public function latestOpenRound(Bid $bid): ?NegotiationMessage
    {
        return NegotiationMessage::where('bid_id', $bid->id)
            ->where('kind', NegotiationMessage::KIND_COUNTER_OFFER)
            ->where('round_status', NegotiationMessage::ROUND_OPEN)
            ->latest('round_number')
            ->first();
    }

    /**
     * Determine which side of the negotiation a user is on. The bid's
     * supplier company is the supplier side; everyone else is the buyer
     * side (the buyer is the RFQ owner).
     */
    private function resolveSide(Bid $bid, User $user): string
    {
        return $user->company_id === $bid->company_id ? 'supplier' : 'buyer';
    }
}
