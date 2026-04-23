<?php

namespace App\Services\Payments;

use App\Models\BankReconciliationPeriod;
use App\Models\BankStatementLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes a reconciliation period for a company. Guards:
 *   - all lines in the period window must be matched (matched_type &
 *     matched_id not null) OR explicitly marked as non-reconcilable via
 *     metadata['excluded'].
 *   - opening_balance + sum(direction-signed amounts) must equal
 *     closing_balance.
 *
 * Once closed, the period is locked — no new lines accepted, no matches
 * changed. To correct a closed period the operator must re-open it with
 * a clear reason (audit trail).
 */
class BankReconClosureService
{
    public function close(BankReconciliationPeriod $period, User $actor, ?string $notes = null): BankReconciliationPeriod
    {
        if ($period->isClosed()) {
            return $period;
        }

        $unmatched = $this->countUnmatched($period);
        if ($unmatched > 0) {
            throw new RuntimeException(__('recon.error_unmatched_lines', ['count' => $unmatched]));
        }

        $this->assertBalancesTieOut($period);

        return DB::transaction(function () use ($period, $actor, $notes) {
            $period->update([
                'status' => BankReconciliationPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'closure_notes' => $notes,
                'lines_unmatched' => 0,
            ]);

            return $period->fresh();
        });
    }

    public function reopen(BankReconciliationPeriod $period, User $actor, string $reason): BankReconciliationPeriod
    {
        if (! $period->isClosed()) {
            return $period;
        }

        $period->update([
            'status' => BankReconciliationPeriod::STATUS_OPEN,
            'closure_notes' => trim(($period->closure_notes ?? '')."\nReopened by {$actor->id}: {$reason}"),
        ]);

        return $period->fresh();
    }

    private function countUnmatched(BankReconciliationPeriod $period): int
    {
        return BankStatementLine::query()
            ->whereHas('statement', fn ($q) => $q->where('company_id', $period->company_id)
                ->whereBetween('statement_date', [$period->period_start, $period->period_end]))
            ->where(function ($q) {
                $q->whereNull('match_status')->orWhere('match_status', '!=', 'matched');
            })
            ->count();
    }

    private function assertBalancesTieOut(BankReconciliationPeriod $period): void
    {
        if ($period->opening_balance === null || $period->closing_balance === null) {
            return; // Balance-less period — skip the tie-out.
        }

        $delta = BankStatementLine::query()
            ->whereHas('statement', fn ($q) => $q->where('company_id', $period->company_id)
                ->whereBetween('statement_date', [$period->period_start, $period->period_end]))
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END), 0) as net")
            ->value('net');

        $expected = round((float) $period->opening_balance + (float) $delta, 2);
        $actual = round((float) $period->closing_balance, 2);
        if (abs($expected - $actual) > 0.02) {
            throw new RuntimeException(__('recon.error_balance_mismatch', [
                'expected' => number_format($expected, 2),
                'actual' => number_format($actual, 2),
            ]));
        }
    }
}
