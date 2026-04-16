<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tiny helper used by the index controllers (PR, RFQ, Bid, Contract,
 * Payment) to stream a CSV export of whatever rows the user is currently
 * looking at — same filters, same scope, just rendered as text/csv.
 *
 * Phase 0 / task 0.9. Stays dependency-free (no maatwebsite/excel) so the
 * code path is identical in dev and prod, and works on the cheapest hosts.
 */
trait ExportsCsv
{
    /**
     * Stream an iterable of rows as a CSV download.
     *
     * @param  iterable<int,array<string,mixed>>  $rows  Each row is a
     *                                                   flat assoc array. Keys of the FIRST row become the headers,
     *                                                   later rows are normalised against those keys.
     * @param  string  $filename  The download filename
     *                            (without timestamp). A timestamp suffix is appended automatically.
     */
    protected function streamCsv(iterable $rows, string $filename): StreamedResponse
    {
        $stamped = pathinfo($filename, PATHINFO_FILENAME)
            .'-'.now()->format('Ymd-His')
            .'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 (Arabic) without mojibake.
            fwrite($out, "\xEF\xBB\xBF");

            $headers = null;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ($headers === null) {
                    $headers = array_keys($row);
                    fputcsv($out, $headers);
                }
                $line = [];
                foreach ($headers as $k) {
                    $v = $row[$k] ?? '';
                    if (is_array($v) || is_object($v)) {
                        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                    $line[] = (string) $v;
                }
                fputcsv($out, $line);
            }

            // Empty result: still emit headers if the caller provided defaults
            // upstream — otherwise just produce an empty file with the BOM.
            if ($headers === null) {
                fputcsv($out, ['(no rows)']);
            }

            fclose($out);
        }, $stamped, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Convenience: detect `?export=csv` on the request. */
    protected function isCsvExport(Request $request): bool
    {
        return strtolower((string) $request->query('export', '')) === 'csv';
    }
}
