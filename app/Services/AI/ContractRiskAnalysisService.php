<?php

namespace App\Services\AI;

use App\Models\Contract;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 5 / Sprint B — automated contract risk review. Reads the contract
 * terms, payment schedule, parties, and currency mix and returns a list
 * of risk flags categorised by severity.
 *
 * Live mode uses Claude with a system prompt scoped to procurement risk
 * (one-sided indemnities, missing IP clauses, payment-on-acceptance with
 * no inspection deadline, etc.). Mock mode uses a tiny rule engine.
 */
class ContractRiskAnalysisService
{
    public function __construct(private readonly AnthropicClient $client)
    {
    }

    /**
     * Analyse a contract and return risk findings:
     *
     *   [
     *     'success'    => bool,
     *     'source'     => 'claude'|'rules',
     *     'overall'    => 'low'|'medium'|'high',
     *     'score'      => 0-100,  // higher = riskier
     *     'findings'   => [
     *       ['severity' => 'high'|'medium'|'low', 'category' => 'payment'|'liability'|...,
     *        'title' => '...', 'description' => '...', 'recommendation' => '...'],
     *     ],
     *   ]
     *
     * Cached per-contract by (id, version) so re-amending the contract
     * invalidates the cache automatically.
     */
    public function analyse(Contract $contract): array
    {
        $cacheKey = 'risk:' . $contract->id . ':' . ($contract->version ?? 1) . ':' . ($contract->updated_at?->timestamp ?? 0);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($contract) {
            if ($this->client->isConfigured()) {
                $live = $this->callClaude($contract);
                if ($live) {
                    return $live;
                }
            }
            return $this->ruleBasedAnalysis($contract);
        });
    }

    private function callClaude(Contract $contract): ?array
    {
        $system = <<<TXT
You are a contract risk analyst for a B2B procurement platform. Read the contract data and identify risks across these categories:
  - payment (advance %, escrow gaps, currency mismatches)
  - liability (one-sided indemnities, unlimited damages)
  - delivery (vague timelines, no penalties for late delivery)
  - quality (no inspection clause, no rejection right)
  - ip (missing ownership clauses for custom work)
  - termination (no exit ramp, harsh cancellation fees)
  - jurisdiction (vague governing law, no arbitration clause)

Respond with ONLY a JSON object:
{
  "overall": "low|medium|high",
  "score": 0,
  "findings": [
    {"severity": "high|medium|low", "category": "...", "title": "...", "description": "...", "recommendation": "..."}
  ]
}

Score is 0-100 where higher = riskier. Findings sorted by severity.
TXT;

        $payload = [
            'title'            => $contract->title,
            'total_amount'     => (float) $contract->total_amount,
            'currency'         => $contract->currency,
            'parties'          => $contract->parties,
            'payment_schedule' => $contract->payment_schedule,
            'terms'            => is_array($contract->terms) ? $contract->terms : (is_string($contract->terms) ? json_decode($contract->terms, true) : []),
            'start_date'       => $contract->start_date?->toDateString(),
            'end_date'         => $contract->end_date?->toDateString(),
        ];

        $parsed = $this->client->send($system, json_encode($payload, JSON_UNESCAPED_UNICODE), expectJson: true, maxTokens: 2000);
        if (!$parsed) {
            return null;
        }

        return [
            'success'  => true,
            'source'   => 'claude',
            'overall'  => (string) ($parsed['overall'] ?? 'medium'),
            'score'    => max(0, min(100, (int) ($parsed['score'] ?? 50))),
            'findings' => array_map(fn ($f) => [
                'severity'       => (string) ($f['severity'] ?? 'medium'),
                'category'       => (string) ($f['category'] ?? 'general'),
                'title'          => (string) ($f['title'] ?? ''),
                'description'    => (string) ($f['description'] ?? ''),
                'recommendation' => (string) ($f['recommendation'] ?? ''),
            ], (array) ($parsed['findings'] ?? [])),
        ];
    }

    /**
     * Rule-based fallback. Inspects the structured fields we already have
     * (payment_schedule, parties, currency, dates) and flags the things a
     * Claude prompt would flag too. Coverage isn't as deep but it gives
     * the buyer something useful even without an API key.
     */
    private function ruleBasedAnalysis(Contract $contract): array
    {
        $findings = [];
        $score    = 0;

        $schedule = (array) ($contract->payment_schedule ?? []);

        // Rule 1 — large advance with no escrow.
        $advance = collect($schedule)->firstWhere('milestone', 'advance');
        if ($advance && (int) ($advance['percentage'] ?? 0) >= 50 && !$contract->escrow_account_id) {
            $findings[] = [
                'severity'       => 'high',
                'category'       => 'payment',
                'title'          => 'Large advance without escrow',
                'description'    => 'The contract requires more than 50% upfront payment without escrow protection.',
                'recommendation' => 'Activate escrow before making the advance payment.',
            ];
            $score += 25;
        }

        // Rule 2 — no inspection / on-delivery release condition anywhere.
        $hasDeliveryGate = collect($schedule)->contains(fn ($m) => ($m['release_condition'] ?? null) === 'on_delivery');
        if (!$hasDeliveryGate && count($schedule) > 0) {
            $findings[] = [
                'severity'       => 'medium',
                'category'       => 'quality',
                'title'          => 'No delivery-gated milestone',
                'description'    => 'No payment milestone is gated on delivery — funds may be released before goods arrive.',
                'recommendation' => 'Tie at least the largest milestone to on_delivery release.',
            ];
            $score += 15;
        }

        // Rule 3 — currency != AED for a UAE buyer (tiny tax/FX exposure).
        if ($contract->currency && $contract->currency !== 'AED') {
            $findings[] = [
                'severity'       => 'low',
                'category'       => 'payment',
                'title'          => 'Foreign-currency contract',
                'description'    => sprintf('Contract is denominated in %s. FX exposure between signing and final payment is your responsibility.', $contract->currency),
                'recommendation' => 'Lock a forward FX rate with your bank or hedge inside escrow.',
            ];
            $score += 5;
        }

        // Rule 4 — very short delivery window relative to amount.
        if ($contract->start_date && $contract->end_date) {
            $days = (int) $contract->start_date->diffInDays($contract->end_date);
            $value = (float) $contract->total_amount;
            if ($days > 0 && $days < 7 && $value > 50_000) {
                $findings[] = [
                    'severity'       => 'medium',
                    'category'       => 'delivery',
                    'title'          => 'Aggressive delivery window for contract value',
                    'description'    => sprintf('A %s contract delivered in %d days is unusual.', number_format($value, 2), $days),
                    'recommendation' => 'Confirm the supplier has stock on hand or extend the delivery window.',
                ];
                $score += 10;
            }
        }

        // Rule 5 — missing or extremely thin terms section.
        $terms = $contract->terms;
        $termsLength = is_string($terms) ? strlen($terms) : (is_array($terms) ? count($terms) * 100 : 0);
        if ($termsLength < 200) {
            $findings[] = [
                'severity'       => 'medium',
                'category'       => 'liability',
                'title'          => 'Sparse contract terms',
                'description'    => 'The terms section is shorter than expected for a commercial contract — liability, termination, and dispute clauses may be missing.',
                'recommendation' => 'Add explicit clauses for liability cap, termination, and dispute resolution.',
            ];
            $score += 10;
        }

        $overall = $score >= 35 ? 'high' : ($score >= 15 ? 'medium' : 'low');

        return [
            'success'  => true,
            'source'   => 'rules',
            'overall'  => $overall,
            'score'    => $score,
            'findings' => $findings,
        ];
    }
}
