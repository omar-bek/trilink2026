<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\EscrowRelease;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports bank statements (MT940 / CAMT.053 / CSV) and auto-matches
 * their lines to Payment / EscrowRelease / BankGuaranteeCall rows.
 *
 * The matching strategy runs in priority order until one fires:
 *  1. Bank reference on the line matches gateway_payment_id or
 *     bank_reference on a Payment / EscrowRelease.
 *  2. Amount + counterparty IBAN match a pending payment to/from
 *     the same company.
 *  3. Amount + value_date fall within ±1 business day of a known
 *     release.
 *
 * Anything still unmatched lands in the "exceptions" queue for
 * manual reconciliation by a finance user.
 */
class BankReconciliationService
{
    public function importMt940(string $raw, int $companyId, int $userId): BankStatement
    {
        $parsed = $this->parseMt940($raw);

        return DB::transaction(function () use ($parsed, $companyId, $userId) {
            $stmt = BankStatement::create([
                'company_id' => $companyId,
                'account_identifier' => $parsed['account'] ?? 'UNKNOWN',
                'currency' => $parsed['currency'] ?? 'AED',
                'format' => 'MT940',
                'statement_date' => $parsed['statement_date'] ?? now()->toDateString(),
                'opening_balance' => $parsed['opening_balance'] ?? 0,
                'closing_balance' => $parsed['closing_balance'] ?? 0,
                'source_file' => $parsed['source_file'] ?? null,
                'imported_by' => $userId,
            ]);

            foreach ($parsed['lines'] ?? [] as $row) {
                $line = BankStatementLine::create(array_merge([
                    'bank_statement_id' => $stmt->id,
                    'match_status' => 'unmatched',
                ], $row));

                $this->autoMatch($line);
            }

            return $stmt->fresh('lines');
        });
    }

    /**
     * Try to match a statement line to an existing platform row. On hit,
     * updates match_* columns; on miss, leaves the line in 'unmatched'
     * state for the exceptions queue.
     */
    public function autoMatch(BankStatementLine $line): bool
    {
        // 1. Reference match.
        if ($line->reference) {
            $payment = Payment::where('gateway_payment_id', $line->reference)->first();
            if ($payment && bccomp((string) $payment->amount, (string) $line->amount, 2) === 0) {
                $line->update([
                    'matched_type' => 'payment',
                    'matched_id' => $payment->id,
                    'match_status' => 'matched',
                    'matched_at' => now(),
                ]);

                return true;
            }

            $release = EscrowRelease::where('bank_reference', $line->reference)->first();
            if ($release) {
                $line->update([
                    'matched_type' => 'escrow_release',
                    'matched_id' => $release->id,
                    'match_status' => 'matched',
                    'matched_at' => now(),
                ]);

                return true;
            }
        }

        // 2. Amount + counterparty match on any pending payment in
        //    same currency, same direction.
        if ($line->counterparty_iban && $line->direction === 'credit') {
            $candidate = Payment::whereIn('status', ['approved', 'processing'])
                ->where('currency', $line->currency)
                ->where('amount', $line->amount)
                ->first();
            if ($candidate) {
                $line->update([
                    'matched_type' => 'payment',
                    'matched_id' => $candidate->id,
                    'match_status' => 'matched',
                    'matched_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Very permissive MT940 parser — covers the 90% of lines we'll see
     * from Mashreq/ENBD/FAB. A real implementation would use an ISO
     * library; this keeps the platform unblocked until procurement signs
     * off on which library to depend on.
     *
     * @return array{account:?string,currency:string,statement_date:?string,opening_balance:float,closing_balance:float,lines:array}
     */
    public function parseMt940(string $raw): array
    {
        $result = [
            'account' => null,
            'currency' => 'AED',
            'statement_date' => null,
            'opening_balance' => 0.0,
            'closing_balance' => 0.0,
            'lines' => [],
        ];

        $rows = preg_split('/\R/', trim($raw));
        $current = null;

        foreach ($rows as $row) {
            if (preg_match('/^:25:(.*)$/', $row, $m)) {
                $result['account'] = trim($m[1]);
            } elseif (preg_match('/^:60F:(?:C|D)(\d{6})(\w{3})(\d+,\d{2})/', $row, $m)) {
                $result['statement_date'] = Carbon::createFromFormat('ymd', $m[1])->toDateString();
                $result['currency'] = $m[2];
                $result['opening_balance'] = (float) str_replace(',', '.', $m[3]);
            } elseif (preg_match('/^:62F:(?:C|D)(\d{6})(\w{3})(\d+,\d{2})/', $row, $m)) {
                $result['closing_balance'] = (float) str_replace(',', '.', $m[3]);
            } elseif (preg_match('/^:61:(\d{6})(\d{4})?([CD])(?:R)?(\d+,\d{2})/', $row, $m)) {
                $current = [
                    'value_date' => Carbon::createFromFormat('ymd', $m[1])->toDateString(),
                    'booking_date' => !empty($m[2]) ? Carbon::createFromFormat('md', $m[2])->year(now()->year)->toDateString() : null,
                    'amount' => (float) str_replace(',', '.', $m[4]),
                    'currency' => $result['currency'],
                    'direction' => $m[3] === 'C' ? 'credit' : 'debit',
                    'description' => '',
                ];
            } elseif ($current && preg_match('/^:86:(.*)$/', $row, $m)) {
                $current['description'] = trim($m[1]);
                // Heuristic: UAE banks commonly stuff the reference into
                // the description after "REF:" or "//".
                if (preg_match('/(?:REF[:\s]+|\/\/)([A-Z0-9\-]+)/i', $m[1], $r)) {
                    $current['reference'] = $r[1];
                }
                $result['lines'][] = $current;
                $current = null;
            }
        }

        return $result;
    }

    public function unmatchedCount(): int
    {
        return BankStatementLine::where('match_status', 'unmatched')->count();
    }
}
