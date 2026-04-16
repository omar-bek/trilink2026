<?php

namespace App\Services\Esg;

use App\Models\Company;
use App\Models\EsgQuestionnaire;
use App\Models\User;

/**
 * Phase 8 — computes the per-pillar ESG score from a questionnaire's
 * raw answers. The questionnaire is intentionally short (≈15 questions
 * spread across the three pillars) so suppliers actually fill it in
 * instead of bouncing.
 *
 * Scoring model:
 *   - Each question has a weight (1-3) and an answer-to-points map.
 *   - Pillar score = (sum of points / max possible) × 100.
 *   - Overall = arithmetic mean of the three pillars.
 *   - Grade is a coarse 5-band map (A ≥ 80, B ≥ 65, C ≥ 50, D ≥ 35, F).
 *
 * The question definitions live as a static array on this class so a
 * future revision can add a question without touching the database
 * schema — only existing answers under the same key carry forward.
 */
class EsgScoringService
{
    /**
     * The complete questionnaire. Keys are stable identifiers used in
     * the answers JSON; the UI form looks them up via __('esg.q_<key>').
     *
     * Each question's `points` map maps the user-selected option to a
     * point count. `weight` multiplies that score so high-impact
     * questions (energy mix, board independence) outweigh nice-to-haves.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function questions(): array
    {
        return [
            // Environmental
            'env_energy_mix' => [
                'pillar' => 'environmental', 'weight' => 3,
                'options' => ['mostly_fossil' => 0, 'mixed' => 1, 'majority_renewable' => 2, 'fully_renewable' => 3],
            ],
            'env_emissions_tracked' => [
                'pillar' => 'environmental', 'weight' => 2,
                'options' => ['no' => 0, 'scope_1' => 1, 'scope_1_2' => 2, 'scope_1_2_3' => 3],
            ],
            'env_waste_program' => [
                'pillar' => 'environmental', 'weight' => 1,
                'options' => ['none' => 0, 'partial' => 1, 'comprehensive' => 2],
            ],
            'env_water_program' => [
                'pillar' => 'environmental', 'weight' => 1,
                'options' => ['none' => 0, 'monitored' => 1, 'reduction_target' => 2],
            ],
            'env_iso14001' => [
                'pillar' => 'environmental', 'weight' => 2,
                'options' => ['no' => 0, 'in_progress' => 1, 'certified' => 3],
            ],

            // Social
            'soc_living_wage' => [
                'pillar' => 'social', 'weight' => 3,
                'options' => ['no' => 0, 'partial' => 1, 'all_employees' => 3],
            ],
            'soc_health_safety' => [
                'pillar' => 'social', 'weight' => 2,
                'options' => ['none' => 0, 'basic' => 1, 'iso45001' => 3],
            ],
            'soc_diversity_policy' => [
                'pillar' => 'social', 'weight' => 1,
                'options' => ['no' => 0, 'in_progress' => 1, 'published_metrics' => 2],
            ],
            'soc_grievance_mechanism' => [
                'pillar' => 'social', 'weight' => 2,
                'options' => ['none' => 0, 'internal_only' => 1, 'independent_third_party' => 2],
            ],
            'soc_supplier_audits' => [
                'pillar' => 'social', 'weight' => 2,
                'options' => ['no' => 0, 'self_declared' => 1, 'third_party_audited' => 3],
            ],

            // Governance
            'gov_board_independence' => [
                'pillar' => 'governance', 'weight' => 3,
                'options' => ['less_25' => 0, '25_50' => 1, 'over_50' => 2],
            ],
            'gov_anti_corruption' => [
                'pillar' => 'governance', 'weight' => 3,
                'options' => ['none' => 0, 'policy' => 1, 'training_and_audit' => 3],
            ],
            'gov_whistleblower' => [
                'pillar' => 'governance', 'weight' => 1,
                'options' => ['no' => 0, 'yes' => 2],
            ],
            'gov_data_privacy' => [
                'pillar' => 'governance', 'weight' => 2,
                'options' => ['none' => 0, 'policy' => 1, 'gdpr_compliant' => 2],
            ],
            'gov_audited_financials' => [
                'pillar' => 'governance', 'weight' => 2,
                'options' => ['no' => 0, 'internal' => 1, 'big_four' => 3],
            ],
        ];
    }

    /**
     * Compute scores for the given answers and persist them as a
     * questionnaire row on the company. Returns the persisted model.
     */
    public function score(Company $company, array $answers, ?User $submittedBy = null): EsgQuestionnaire
    {
        $questions = self::questions();

        $pillarScores = [
            'environmental' => $this->scorePillar($questions, $answers, 'environmental'),
            'social' => $this->scorePillar($questions, $answers, 'social'),
            'governance' => $this->scorePillar($questions, $answers, 'governance'),
        ];

        $overall = (int) round(($pillarScores['environmental'] + $pillarScores['social'] + $pillarScores['governance']) / 3);

        return EsgQuestionnaire::updateOrCreate(
            ['company_id' => $company->id],
            [
                'environmental_score' => $pillarScores['environmental'],
                'social_score' => $pillarScores['social'],
                'governance_score' => $pillarScores['governance'],
                'overall_score' => $overall,
                'grade' => $this->grade($overall),
                'answers' => $answers,
                'submitted_by' => $submittedBy?->id,
                'submitted_at' => now(),
            ],
        );
    }

    /**
     * Score one pillar: sum (points × weight) across every question
     * matching the pillar, normalised to 0-100.
     */
    private function scorePillar(array $questions, array $answers, string $pillar): int
    {
        $earned = 0;
        $maxPossible = 0;

        foreach ($questions as $key => $q) {
            if ($q['pillar'] !== $pillar) {
                continue;
            }
            $weight = (int) $q['weight'];
            $options = $q['options'];
            $maxOption = max($options);
            $maxPossible += $maxOption * $weight;

            $answer = $answers[$key] ?? null;
            if ($answer !== null && isset($options[$answer])) {
                $earned += $options[$answer] * $weight;
            }
        }

        if ($maxPossible <= 0) {
            return 0;
        }

        return (int) round(($earned / $maxPossible) * 100);
    }

    private function grade(int $overall): string
    {
        return match (true) {
            $overall >= 80 => 'A',
            $overall >= 65 => 'B',
            $overall >= 50 => 'C',
            $overall >= 35 => 'D',
            default => 'F',
        };
    }
}
