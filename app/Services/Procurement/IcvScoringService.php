<?php

namespace App\Services\Procurement;

use App\Models\Bid;
use App\Models\IcvCertificate;
use App\Models\Rfq;
use Illuminate\Support\Collection;

/**
 * Phase 4 (UAE Compliance Roadmap) — composite-score calculator for
 * bid evaluation that blends price competitiveness with the supplier's
 * In-Country Value (ICV) score.
 *
 * Formula:
 *
 *     composite = (1 - w) * price_score + w * icv_score
 *
 * where
 *
 *     w           = rfq.icv_weight_percentage / 100   (0..0.5)
 *     price_score = 100 * lowest_price / this_price   (lowest = 100)
 *     icv_score   = supplier's latest verified non-expired ICV score
 *                   from icv_certificates, 0 if none
 *
 * Edge cases:
 *
 *   - When `w = 0` the formula collapses to pure price scoring, which
 *     matches the platform's pre-Phase-4 behaviour exactly. Calling
 *     scoreBid() on a non-ICV RFQ is a safe no-op that returns the
 *     price score so existing comparison code works unchanged.
 *
 *   - When `w > 0` and the supplier has NO ICV certificate, their ICV
 *     score is 0 (not null) — that's the legally correct way to model
 *     "no demonstrable in-country value" for bid evaluation purposes.
 *
 *   - `icv_minimum_score` on the RFQ flags suppliers below the cutoff
 *     as DISQUALIFIED. They still get a composite score (so the buyer
 *     can see how close they were) but `meetsMinimum()` returns false,
 *     and the compare-bids view drops them to the bottom of the list.
 */
class IcvScoringService
{
    /**
     * Score a single bid in the context of all bids on the same RFQ.
     * The "all bids" collection is needed for the price normalisation
     * — the lowest price gets 100% on the price axis.
     *
     * @param  \Illuminate\Support\Collection<int, Bid>  $allBidsOnRfq
     */
    public function scoreBid(Bid $bid, Collection $allBidsOnRfq, ?Rfq $rfq = null): array
    {
        $rfq = $rfq ?? $bid->rfq;
        $weight = $this->normalizedWeight($rfq);

        $priceScore = $this->priceScoreFor($bid, $allBidsOnRfq);
        $icvScore   = $this->icvScoreFor($bid);

        // composite = (1-w) * price + w * icv
        // When w = 0 we still want the composite to equal the price
        // score so the compare-bids view can sort by composite uniformly.
        $composite = round((1 - $weight) * $priceScore + $weight * $icvScore, 2);

        return [
            'price_score'    => $priceScore,
            'icv_score'      => $icvScore,
            'composite'      => $composite,
            'icv_weight'     => $weight,
            'meets_minimum'  => $this->meetsMinimum($icvScore, $rfq),
        ];
    }

    /**
     * Build a sorted ranking of every bid on an RFQ. Bids that fall
     * below the RFQ's icv_minimum_score (if set) are pushed to the
     * bottom of the list with a `disqualified = true` flag — buyers
     * still see them, just not at the top.
     *
     * @param  \Illuminate\Support\Collection<int, Bid>  $bids
     * @return array<int, array<string, mixed>>
     */
    public function rankBids(Collection $bids, ?Rfq $rfq = null): array
    {
        if ($bids->isEmpty()) {
            return [];
        }
        $rfq = $rfq ?? $bids->first()?->rfq;

        $rows = $bids->map(function (Bid $bid) use ($bids, $rfq) {
            $bid->loadMissing('company');
            $score = $this->scoreBid($bid, $bids, $rfq);
            return [
                'bid_id'        => $bid->id,
                'company_id'    => $bid->company_id,
                'company_name'  => $bid->company?->name ?? '—',
                'price'         => (float) $bid->price,
                'currency'      => $bid->currency ?? 'AED',
                'price_score'   => $score['price_score'],
                'icv_score'     => $score['icv_score'],
                'composite'     => $score['composite'],
                'meets_minimum' => $score['meets_minimum'],
                'disqualified'  => !$score['meets_minimum'],
            ];
        })->all();

        // Sort: qualified bids first (descending composite), disqualified
        // last (descending composite within their group). Tie-break on
        // raw price ascending so the cheapest bid wins ties.
        usort($rows, function ($a, $b) {
            if ($a['disqualified'] !== $b['disqualified']) {
                return $a['disqualified'] ? 1 : -1;
            }
            $cmp = $b['composite'] <=> $a['composite'];
            return $cmp !== 0 ? $cmp : ($a['price'] <=> $b['price']);
        });

        // Stamp 1-indexed ranks AFTER sorting so the buyer sees a clean
        // 1, 2, 3, ... regardless of disqualification.
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        return $rows;
    }

    private function normalizedWeight(?Rfq $rfq): float
    {
        if (!$rfq) {
            return 0.0;
        }
        $w = (int) ($rfq->icv_weight_percentage ?? 0);
        // Clamp to the safe 0..50 range — anything outside that is a bug.
        $w = max(0, min(50, $w));
        return $w / 100;
    }

    /**
     * Price score: lowest bid gets 100, others scale inversely. Returns
     * 0 when the lowest price is also 0 (degenerate RFQ — shouldn't
     * happen but bail safely instead of dividing by zero).
     */
    private function priceScoreFor(Bid $bid, Collection $allBids): float
    {
        $lowest = (float) $allBids->min('price');
        $thisPrice = (float) $bid->price;

        if ($lowest <= 0 || $thisPrice <= 0) {
            return 0.0;
        }
        return round(($lowest / $thisPrice) * 100, 2);
    }

    /**
     * Pick the supplier's best usable ICV score. Returns 0 when no
     * verified non-expired certificate exists — that's the legally
     * defensible default ("no demonstrable in-country value").
     */
    private function icvScoreFor(Bid $bid): float
    {
        $bid->loadMissing('company');
        $company = $bid->company;
        if (!$company) {
            return 0.0;
        }

        $score = $company->latestActiveIcvScore();
        return $score !== null ? (float) $score : 0.0;
    }

    private function meetsMinimum(float $icvScore, ?Rfq $rfq): bool
    {
        if (!$rfq || $rfq->icv_minimum_score === null) {
            return true;
        }
        return $icvScore >= (float) $rfq->icv_minimum_score;
    }
}
