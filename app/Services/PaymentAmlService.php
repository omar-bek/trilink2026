<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentScreening;
use App\Models\SanctionsScreening;

/**
 * AML/sanctions screening hook called at two gates:
 *   - pre_approval : right before a payment transitions to APPROVED
 *   - pre_release  : right before escrow funds are released
 *
 * Today the check is a thin layer over the existing `sanctions_screenings`
 * table — every party company is screened and the most recent result is
 * cached on a per-payment row so an auditor can show that each movement
 * of value was screened at the moment it happened (CBUAE AML rulebook
 * requires point-in-time evidence, not "we screen the customer once").
 *
 * Expansion hooks:
 *   - `threshold_checks` : detect structuring (multiple payments below
 *     AED 55,000 in 24h to same counterparty)
 *   - `goaml_submit`     : auto-file an STR to goAML for HIT results
 *
 * For now those land as TODO markers so the service compiles; the
 * wiring comes in a follow-up PR.
 */
class PaymentAmlService
{
    /**
     * Screen both parties of a payment and persist a PaymentScreening
     * row per entity. Returns 'clean' | 'hit' | 'review' | 'error'.
     */
    public function screen(Payment $payment, string $stage): string
    {
        $entities = [
            'payer' => $payment->company_id,
            'recipient' => $payment->recipient_company_id,
        ];

        $worstResult = 'clean';

        foreach ($entities as $role => $companyId) {
            if (! $companyId) {
                continue;
            }
            $result = $this->screenCompany((int) $companyId);

            PaymentScreening::create([
                'payment_id' => $payment->id,
                'stage' => $stage,
                'result' => $result,
                'screened_entity' => $role,
                'screened_company_id' => $companyId,
                'findings' => $this->findingsFor((int) $companyId),
            ]);

            // worst-case aggregation: hit > review > error > clean
            if ($result === 'hit') {
                $worstResult = 'hit';
            } elseif ($result === 'review' && $worstResult !== 'hit') {
                $worstResult = 'review';
            } elseif ($result === 'error' && $worstResult === 'clean') {
                $worstResult = 'error';
            }
        }

        // Structuring detector — any 3+ payments in 24h to the same
        // recipient each under AED 55,000 but totalling over. Cheap
        // query, important signal.
        if ($this->looksStructured($payment)) {
            PaymentScreening::create([
                'payment_id' => $payment->id,
                'stage' => $stage,
                'result' => 'review',
                'screened_entity' => 'pattern',
                'findings' => ['pattern' => 'possible_structuring'],
            ]);
            if ($worstResult === 'clean') {
                $worstResult = 'review';
            }
        }

        return $worstResult;
    }

    /**
     * Get the latest sanctions result for a company and map it to the
     * payment-screening vocabulary. We deliberately use fresh data —
     * never older than 24h — so a stale "clean" from 6 months ago
     * doesn't underwrite today's payment.
     */
    private function screenCompany(int $companyId): string
    {
        $latest = SanctionsScreening::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->first();

        if (! $latest) {
            // Configurable fallback: 'allow' keeps demo tenants unblocked,
            // 'review' forces manual sign-off, 'block' hard-fails. See
            // config/services.php `aml.missing_screening_action`.
            return match (config('services.aml.missing_screening_action', 'allow')) {
                'block' => 'hit',
                'review' => 'review',
                default => 'clean',
            };
        }

        return match ($latest->result) {
            'clean' => 'clean',
            'hit' => 'hit',
            'review' => 'review',
            default => 'error',
        };
    }

    private function findingsFor(int $companyId): ?array
    {
        $latest = SanctionsScreening::where('company_id', $companyId)
            ->latest()
            ->first();

        return $latest?->details;
    }

    /**
     * Flag payment patterns that look like structuring / layering.
     * Threshold chosen to align with CBUAE AML rulebook (AED 55k).
     */
    private function looksStructured(Payment $payment): bool
    {
        $cutoff = now()->subDay();
        $count = Payment::where('company_id', $payment->company_id)
            ->where('recipient_company_id', $payment->recipient_company_id)
            ->where('created_at', '>=', $cutoff)
            ->where('amount', '<', 55000)
            ->count();

        $sum = (float) Payment::where('company_id', $payment->company_id)
            ->where('recipient_company_id', $payment->recipient_company_id)
            ->where('created_at', '>=', $cutoff)
            ->sum('amount');

        return $count >= 3 && $sum >= 55000;
    }

    /**
     * Returns true if this payment is safe to proceed (result = clean).
     * Controllers use this as a gate before approve()/release().
     */
    public function isCleared(string $result): bool
    {
        return $result === 'clean';
    }
}
