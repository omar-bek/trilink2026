<?php

namespace App\Services;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;

/**
 * Reverse-auction logic layered on top of the standard Bid pipeline.
 *
 * The contract:
 *   - placeAuctionBid() enforces window + reserve + decrement, then writes
 *     the bid via the same Bid table everyone else uses. The bid history
 *     view stays unified — auction bids are just rapid-fire bids.
 *   - Anti-snipe extends auction_ends_at when the leader changes inside the
 *     final anti_snipe_seconds. We extend by the same number of seconds so
 *     a 2-minute snipe window keeps repeating until the auction settles.
 *   - leaderboard() returns ranked snapshot for the live UI poller.
 */
class AuctionService
{
    public const BID_REJECTED_NOT_AUCTION = 'not_auction';

    public const BID_REJECTED_NOT_OPEN = 'not_open';

    public const BID_REJECTED_BEFORE_START = 'before_start';

    public const BID_REJECTED_AFTER_END = 'after_end';

    public const BID_REJECTED_BELOW_RESERVE = 'below_reserve';

    public const BID_REJECTED_ABOVE_LEADER = 'above_leader';

    public const BID_REJECTED_DECREMENT = 'decrement';

    public const BID_REJECTED_OWN_RFQ = 'own_rfq';

    /**
     * Place a bid in a live reverse auction.
     *
     * Returns the new Bid on success or a string error code listed above.
     */
    public function placeAuctionBid(Rfq $rfq, int $companyId, int $providerId, float $price, string $currency): Bid|string
    {
        if (! $rfq->is_auction) {
            return self::BID_REJECTED_NOT_AUCTION;
        }
        if ($rfq->company_id === $companyId) {
            return self::BID_REJECTED_OWN_RFQ;
        }

        $now = now();
        if ($rfq->auction_starts_at && $now->lt($rfq->auction_starts_at)) {
            return self::BID_REJECTED_BEFORE_START;
        }
        if ($rfq->auction_ends_at && $now->gt($rfq->auction_ends_at)) {
            return self::BID_REJECTED_AFTER_END;
        }
        if ($rfq->reserve_price !== null && $price < (float) $rfq->reserve_price) {
            return self::BID_REJECTED_BELOW_RESERVE;
        }

        // Reverse auction: a new bid must beat the current leader by at
        // least bid_decrement. Tie or higher = rejected.
        $leader = $this->currentLeader($rfq);
        if ($leader) {
            $maxAllowed = (float) $leader->price - (float) ($rfq->bid_decrement ?? 0);
            if ($price > $maxAllowed) {
                return $rfq->bid_decrement
                    ? self::BID_REJECTED_DECREMENT
                    : self::BID_REJECTED_ABOVE_LEADER;
            }
        }

        return DB::transaction(function () use ($rfq, $companyId, $providerId, $price, $currency, $now) {
            // One bid row per (rfq, company) — we update rather than append.
            // The full history per company stays in audit logs / future
            // auction_bid_history table when we need it.
            $existing = Bid::where('rfq_id', $rfq->id)
                ->where('company_id', $companyId)
                ->first();

            if ($existing) {
                $existing->update([
                    'price' => $price,
                    'currency' => $currency,
                    'status' => BidStatus::SUBMITTED,
                ]);
                $bid = $existing->fresh();
            } else {
                $bid = Bid::create([
                    'rfq_id' => $rfq->id,
                    'company_id' => $companyId,
                    'provider_id' => $providerId,
                    'price' => $price,
                    'currency' => $currency,
                    'status' => BidStatus::SUBMITTED,
                    'items' => [],
                ]);
            }

            // Anti-snipe: if this bid landed inside the final
            // anti_snipe_seconds and it's the new leader, extend the auction.
            $secondsLeft = $now->diffInSeconds($rfq->auction_ends_at, false);
            if ($secondsLeft >= 0 && $secondsLeft <= $rfq->anti_snipe_seconds) {
                $newLeader = $this->currentLeader($rfq->fresh());
                if ($newLeader && $newLeader->id === $bid->id) {
                    $rfq->update([
                        'auction_ends_at' => $rfq->auction_ends_at->addSeconds($rfq->anti_snipe_seconds),
                    ]);
                }
            }

            return $bid;
        });
    }

    /**
     * The current leading bid (lowest price). Used by both the leaderboard
     * UI and the next-bid validation.
     */
    public function currentLeader(Rfq $rfq): ?Bid
    {
        return Bid::where('rfq_id', $rfq->id)
            ->where('status', BidStatus::SUBMITTED)
            ->orderBy('price', 'asc')
            ->orderBy('updated_at', 'asc')
            ->first();
    }

    /**
     * Ranked snapshot for the live UI. Polled from JS every 5s.
     *
     * @return array<int, array{rank:int, company:string, price:float, currency:string, updated:string}>
     */
    public function leaderboard(Rfq $rfq, int $limit = 10): array
    {
        return Bid::with('company')
            ->where('rfq_id', $rfq->id)
            ->where('status', BidStatus::SUBMITTED)
            ->orderBy('price', 'asc')
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn (Bid $b, int $i) => [
                'rank' => $i + 1,
                'company' => $rfq->is_anonymous
                    ? 'Bidder #'.str_pad((string) ($b->company_id * 137 % 9999), 4, '0', STR_PAD_LEFT)
                    : ($b->company?->name ?? '—'),
                'price' => (float) $b->price,
                'currency' => $b->currency ?? 'AED',
                'updated' => $b->updated_at?->toIso8601String() ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Lightweight payload for the live UI poller. Includes leaderboard +
     * server clock so the JS countdown can sync.
     */
    public function liveSnapshot(Rfq $rfq): array
    {
        $rfq->refresh();

        return [
            'rfq_id' => $rfq->id,
            'rfq_number' => $rfq->rfq_number,
            'is_live' => $rfq->isLiveAuction(),
            'auction_ends_at' => $rfq->auction_ends_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'leader_price' => optional($this->currentLeader($rfq))->price,
            'reserve_price' => $rfq->reserve_price ? (float) $rfq->reserve_price : null,
            'bid_decrement' => $rfq->bid_decrement ? (float) $rfq->bid_decrement : null,
            'leaderboard' => $this->leaderboard($rfq, 10),
        ];
    }
}
