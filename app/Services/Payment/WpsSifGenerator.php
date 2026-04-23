<?php

namespace App\Services\Payment;

use App\Models\WpsPayrollBatch;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the Salary Information File (SIF) expected by the UAE Wage
 * Protection System. Every agent bank accepts the same flat-file layout
 * documented by CBUAE:
 *
 *   EDR | employer_eid | bank_code | agent_id | file_creation | net_total | record_count | currency | payment_month | payment_year
 *   EDR header followed by one SCR record per employee.
 *
 * Fields are comma-separated and MUST be written in the exact column
 * order below — MOHRE rejects files that reorder or omit columns. The
 * generator also produces a SHA-256 hash the employer keeps for audit;
 * CBUAE stores the same hash against the submission.
 */
class WpsSifGenerator
{
    public function generate(WpsPayrollBatch $batch): string
    {
        $batch->load('lines');

        $lines = [];

        // EDR — Employer Details Record (header)
        $lines[] = implode(',', [
            'EDR',
            $batch->employer_eid,
            $batch->lines->first()?->bank_code ?? '',
            $batch->agent_id,
            now()->format('YmdHi'),
            number_format((float) $batch->total_net_aed, 2, '.', ''),
            (string) $batch->lines->count(),
            'AED',
            $batch->pay_period_end->format('m'),
            $batch->pay_period_end->format('Y'),
        ]);

        // SCR — one per employee. Salaries in halalas (fils × 100) are
        // NOT required here — AED with 2 decimals is the spec.
        foreach ($batch->lines as $line) {
            $lines[] = implode(',', [
                'SCR',
                $line->employee_lcpn,
                $line->iban,
                $line->bank_code ?? '',
                $batch->pay_period_end->format('Ymd'),
                number_format((float) $line->net_salary, 2, '.', ''),
                'AED',
                (string) $line->working_days,
                (string) $line->leave_days,
                number_format((float) $line->basic_salary, 2, '.', ''),
                number_format((float) $line->housing_allowance, 2, '.', ''),
                number_format((float) $line->other_allowances, 2, '.', ''),
                number_format((float) $line->deductions, 2, '.', ''),
            ]);
        }

        $content = implode("\r\n", $lines)."\r\n";
        $hash = hash('sha256', $content);

        $path = sprintf(
            'wps/%d/%s-%s.sif',
            $batch->company_id,
            $batch->pay_period_end->format('Y-m'),
            substr($hash, 0, 8)
        );

        Storage::disk('local')->put($path, $content);

        $batch->update([
            'sif_file_path' => $path,
            'sif_file_hash' => $hash,
            'status' => 'generated',
        ]);

        return $path;
    }
}
