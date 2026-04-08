<?php

namespace App\Services\AI;

use App\Models\Bid;
use App\Models\Rfq;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 5 / Sprint B — AI-driven counter-offer + concession suggestions
 * for an active negotiation. Used by the negotiation room sidebar so the
 * buyer can see "what would a procurement expert do here?" without having
 * to be one.
 *
 * Two modes:
 *   1. Live (Claude): given the bid + market context (other bids on the
 *      same RFQ + buyer's historical contracts), Claude returns a
 *      recommended counter-price + 3 negotiation talking points.
 *   2. Mock: deterministic suggestions based purely on the spread
 *      between the lowest and highest bids on the RFQ. Always usable.
 */
class NegotiationAssistantService
{
    public function __construct(private readonly AnthropicClient $client)
    {
    }

    /**
     * Suggest a counter-offer for the given bid. Returns:
     *
     *   [
     *     'success'         => bool,
     *     'source'          => 'claude'|'mock',
     *     'recommended_price' => 12000.50,
     *     'currency'        => 'AED',
     *     'rationale'       => 'Two competing bids are 8% lower; supplier has 4.7 rating so worth pushing',
     *     'talking_points'  => ['First point', 'Second point', 'Third point'],
     *     'confidence'      => 0.0-1.0,
     *   ]
     */
    public function suggestCounterOffer(Bid $bid): array
    {
        $bid->loadMissing(['rfq.bids', 'company']);
        $rfq = $bid->rfq;

        if (!$rfq) {
            return $this->mockSuggestion(null, $bid);
        }

        // Cache per (bid, last bid update) so the assistant doesn't
        // re-call the API on every page refresh while the negotiation
        // hasn't moved.
        $cacheKey = 'neg:' . $bid->id . ':' . ($bid->updated_at?->timestamp ?? 0);
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($bid, $rfq) {
            if ($this->client->isConfigured()) {
                $live = $this->callClaude($bid, $rfq);
                if ($live) {
                    return $live;
                }
            }
            return $this->mockSuggestion($rfq, $bid);
        });
    }

    private function callClaude(Bid $bid, Rfq $rfq): ?array
    {
        // Pull the spread of competing bids on this RFQ so Claude has
        // market context. Cap at 10 to keep the prompt small.
        $competing = $rfq->bids
            ->where('id', '!=', $bid->id)
            ->take(10)
            ->map(fn ($b) => [
                'price'    => (float) $b->price,
                'currency' => $b->currency,
                'lead_days' => $b->delivery_time_days,
            ])
            ->values()
            ->all();

        $system = <<<TXT
You are a B2B procurement negotiation assistant. Given an open bid and the surrounding market on the same RFQ, recommend a counter-offer that is realistic, justified, and respects supplier margins.

Respond with ONLY a JSON object:
{
  "recommended_price": 0,
  "currency": "AED",
  "rationale": "one or two sentences",
  "talking_points": ["point 1", "point 2", "point 3"],
  "confidence": 0.0
}

Rules:
- Never recommend more than the current bid price.
- Never recommend below 70% of the current bid (we want to keep the supplier in the game).
- Talking points should be specific (cite the spread, lead-time, history) — not generic.
- Confidence is 0.0-1.0; lower it when there are fewer competing bids to triangulate.
TXT;

        $userPayload = json_encode([
            'rfq_title'      => $rfq->title,
            'category_id'    => $rfq->category_id,
            'current_bid'    => [
                'price'         => (float) $bid->price,
                'currency'      => $bid->currency,
                'lead_days'     => $bid->delivery_time_days,
                'payment_terms' => $bid->payment_terms,
                'supplier'      => $bid->company?->name,
            ],
            'competing_bids' => $competing,
        ], JSON_UNESCAPED_UNICODE);

        $parsed = $this->client->send($system, $userPayload, expectJson: true, maxTokens: 800);
        if (!$parsed) {
            return null;
        }

        // Sanity-clamp the price the LLM suggests so a hallucinated
        // outlier doesn't get pushed straight into the negotiation room.
        $current = (float) $bid->price;
        $price   = max($current * 0.7, min($current, (float) ($parsed['recommended_price'] ?? $current)));

        return [
            'success'           => true,
            'source'            => 'claude',
            'recommended_price' => round($price, 2),
            'currency'          => (string) ($parsed['currency'] ?? $bid->currency ?? 'AED'),
            'rationale'         => (string) ($parsed['rationale'] ?? ''),
            'talking_points'    => array_slice((array) ($parsed['talking_points'] ?? []), 0, 5),
            'confidence'        => max(0.0, min(1.0, (float) ($parsed['confidence'] ?? 0.5))),
        ];
    }

    /**
     * Deterministic mock — recommends 95% of the lowest competing bid
     * (or 92% of the current bid if there are no competitors). Always
     * usable, never depends on a live API key.
     */
    private function mockSuggestion(?Rfq $rfq, Bid $bid): array
    {
        $current   = (float) $bid->price;
        $competing = $rfq?->bids?->where('id', '!=', $bid->id)?->pluck('price')?->map(fn ($p) => (float) $p) ?? collect();
        $lowest    = $competing->min();

        if ($lowest && $lowest < $current) {
            $recommended = round($lowest * 0.95, 2);
            $rationale   = sprintf('Lowest competing bid is %s; recommend matching with a 5%% concession.', number_format($lowest, 2));
        } else {
            $recommended = round($current * 0.92, 2);
            $rationale   = 'No competing bids to compare; suggest an 8% reduction as an opening counter.';
        }

        return [
            'success'           => true,
            'source'            => 'mock',
            'recommended_price' => $recommended,
            'currency'          => $bid->currency ?? 'AED',
            'rationale'         => $rationale,
            'talking_points'    => [
                'Reference the published budget in the original RFQ.',
                'Highlight repeat-buyer potential if you award this contract.',
                'Ask for a longer payment term in exchange for holding the price.',
            ],
            'confidence'        => 0.5,
        ];
    }
}
