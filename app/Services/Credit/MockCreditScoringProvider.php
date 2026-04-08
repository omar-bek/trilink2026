<?php

namespace App\Services\Credit;

/**
 * Deterministic mock credit scoring provider. Phase 2 / Sprint 10 / 2.16.
 *
 * Used for tests, demos, and the H1 deployment until we have signed
 * agreements with AECB / SIMAH / D&B. The score is deterministic from
 * the registration number so the same input always returns the same
 * output (test fixtures and snapshots stay stable).
 *
 * The score distribution is intentionally biased toward "good" so the
 * demo dashboards don't look like every supplier is high-risk:
 *   - 60% land in 700-850 (good / very good)
 *   - 25% land in 500-699 (fair)
 *   - 10% land in 350-499 (poor)
 *   -  5% land below 350  (very poor)
 */
class MockCreditScoringProvider implements CreditScoringProviderInterface
{
    public function code(): string
    {
        return 'mock';
    }

    public function fetchScore(string $registrationNumber, ?string $country = null): array
    {
        $registrationNumber = trim($registrationNumber);
        if ($registrationNumber === '') {
            return [
                'success'     => false,
                'score'       => null,
                'band'        => null,
                'reasons'     => [],
                'reported_at' => null,
                'error'       => 'Empty registration number',
            ];
        }

        // Deterministic-but-spread hash of the input. Modulo 1000 maps
        // straight onto the score scale.
        $seed  = crc32($registrationNumber . '|' . ($country ?? ''));
        $bucket = $seed % 100;

        // Buckets matching the docblock distribution above.
        if ($bucket < 60) {
            $score = 700 + ($seed % 150);          // 700..849
        } elseif ($bucket < 85) {
            $score = 500 + ($seed % 200);          // 500..699
        } elseif ($bucket < 95) {
            $score = 350 + ($seed % 150);          // 350..499
        } else {
            $score = 200 + ($seed % 150);          // 200..349
        }

        return [
            'success'     => true,
            'score'       => $score,
            'band'        => $this->scoreBand($score),
            'reasons'     => $this->reasons($score),
            'reported_at' => now()->toIso8601String(),
            'error'       => null,
        ];
    }

    /**
     * Map a numeric score to a 4-band qualitative label. Matches the
     * AECB scale so a future provider swap doesn't break consumers.
     */
    private function scoreBand(int $score): string
    {
        return match (true) {
            $score >= 750 => 'excellent',
            $score >= 650 => 'good',
            $score >= 500 => 'fair',
            default       => 'poor',
        };
    }

    /**
     * Plausible explanatory factors per band. Real bureaux return their
     * own structured reason codes; the mock just produces a believable
     * shortlist so the UI has something to render.
     *
     * @return array<int, string>
     */
    private function reasons(int $score): array
    {
        if ($score >= 750) {
            return ['Long credit history', 'Low utilisation', 'No defaults on file'];
        }
        if ($score >= 650) {
            return ['Stable payment history', 'Moderate utilisation'];
        }
        if ($score >= 500) {
            return ['Limited credit history', 'Some late payments in last 12 months'];
        }
        return ['Recent default on file', 'High utilisation', 'Short credit history'];
    }
}
