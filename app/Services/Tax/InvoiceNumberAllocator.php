<?php

namespace App\Services\Tax;

use App\Models\InvoiceNumberSequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Atomic sequential number allocator for tax invoices and credit notes.
 *
 * The FTA requires every tax invoice to carry a "unique sequential number".
 * Two parallel `IssueTaxInvoiceJob`s for the same company in the same year
 * must NEVER mint the same number — that would cascade into a duplicate
 * unique-key violation on tax_invoices.invoice_number, and worse, a buyer
 * who got the second invoice would have a "phantom" record on the books.
 *
 * Strategy:
 *
 *   1. Run inside a DB transaction.
 *   2. Read the (company, series, year) row WITH row lock (FOR UPDATE).
 *   3. Increment next_value, write it back, commit.
 *   4. Format the allocated number from the OLD next_value: e.g.
 *      INV-2026-000123 means "the 123rd invoice issued in 2026".
 *
 * The lock is held only between SELECT and UPDATE — typically <1ms. The
 * second concurrent caller blocks for that microscopic window then reads
 * the incremented value.
 *
 * The format is locale-independent: zero-padded to 6 digits, ASCII digits
 * only (so it doesn't break when arabic-locale users issue invoices —
 * sequential numbers must be machine-parseable in any reading direction).
 *
 * Year reset: each calendar year starts a fresh counter. If a company
 * issues 1,200 invoices in 2026 and then 14 in early 2027, the first 2027
 * invoice is INV-2027-000001, not INV-2027-001201.
 */
class InvoiceNumberAllocator
{
    public const SERIES_INVOICE     = 'INV';
    public const SERIES_CREDIT_NOTE = 'CN';

    /**
     * Allocate the next available number in the given (company, series)
     * sequence for the current year. Caller is responsible for the
     * surrounding transaction if it wants the number to be rolled back
     * alongside the invoice creation — but this method works fine on its
     * own (it opens its own DB transaction for the lock).
     *
     * @return string e.g. "INV-2026-000123"
     */
    public function allocate(int $companyId, string $series, ?CarbonImmutable $on = null): string
    {
        $on = $on ?? CarbonImmutable::now();
        $year = (int) $on->year;

        if (!in_array($series, [self::SERIES_INVOICE, self::SERIES_CREDIT_NOTE], true)) {
            throw new RuntimeException("Unknown invoice series: {$series}");
        }

        return DB::transaction(function () use ($companyId, $series, $year) {
            // First-call insert path: ensure the sequence row exists.
            // We use insertOrIgnore so two concurrent callers don't both
            // create the row — one wins, the other no-ops, and BOTH then
            // proceed into the row lock below.
            DB::table('invoice_number_sequences')->insertOrIgnore([
                'company_id' => $companyId,
                'series'     => $series,
                'year'       => $year,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lock the sequence row. Anyone else who comes in for the same
            // (company, series, year) tuple will block on the FOR UPDATE
            // until our transaction commits.
            $row = InvoiceNumberSequence::query()
                ->where('company_id', $companyId)
                ->where('series', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                // Should be impossible after the insertOrIgnore above —
                // bail loudly so the bug surfaces, don't continue with a
                // wrong number.
                throw new RuntimeException(
                    "Failed to claim invoice number row for company={$companyId} series={$series} year={$year}"
                );
            }

            $current = (int) $row->next_value;

            $row->next_value = $current + 1;
            $row->save();

            // Format: SERIES-YYYY-NNNNNN (6 zero-padded digits).
            // 6 digits gives us 999,999 invoices per company per year —
            // more than enough for the foreseeable future.
            return sprintf('%s-%d-%06d', $series, $year, $current);
        });
    }

    /**
     * Convenience: peek at what the next number would be without
     * actually allocating it. Useful for previews / dry runs only —
     * NEVER call this and then issue an invoice with that number,
     * because between peek and issue another caller may have allocated.
     */
    public function peek(int $companyId, string $series, ?CarbonImmutable $on = null): string
    {
        $on = $on ?? CarbonImmutable::now();
        $year = (int) $on->year;

        $row = InvoiceNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('series', $series)
            ->where('year', $year)
            ->first();

        $next = $row ? (int) $row->next_value : 1;

        return sprintf('%s-%d-%06d', $series, $year, $next);
    }
}
