<?php

namespace App\Console\Commands;

use App\Models\TaxInvoice;
use App\Services\Tax\TaxInvoiceService;
use Illuminate\Console\Command;

/**
 * Re-render every stored tax-invoice PDF using the current template.
 *
 * Use case: the template (or a shared helper like ArabicShaper) changed
 * in a way that affects how existing invoices visually render. The row
 * data itself is unchanged — we're only producing a fresh PDF from the
 * same facts and overwriting the file on disk. pdf_sha256 is updated so
 * anyone verifying the chain knows the render was refreshed.
 *
 * Safe to run repeatedly. Skips voided invoices by default because their
 * stored PDF is the legal record of the original issuance and should not
 * be rewritten; pass --include-voided to override.
 */
class RerenderTaxInvoicePdfs extends Command
{
    protected $signature = 'tax-invoices:rerender-pdfs
                            {--include-voided : Re-render voided invoices too}
                            {--id=* : Only re-render specific invoice IDs}';

    protected $description = 'Re-render stored tax invoice PDFs with the current template';

    public function handle(TaxInvoiceService $service): int
    {
        $query = TaxInvoice::query();

        if (! $this->option('include-voided')) {
            $query->where('status', '!=', TaxInvoice::STATUS_VOIDED);
        }

        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }

        $count = $query->count();
        $this->info("Re-rendering {$count} tax invoice(s)…");

        $bar = $this->output->createProgressBar($count);
        $failed = 0;

        $query->chunkById(50, function ($invoices) use ($service, $bar, &$failed) {
            foreach ($invoices as $invoice) {
                try {
                    $service->renderAndStorePdf($invoice);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("  #{$invoice->id} {$invoice->invoice_number}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($failed > 0) {
            $this->warn("Completed with {$failed} failure(s).");
            return self::FAILURE;
        }

        $this->info('All tax invoice PDFs re-rendered.');
        return self::SUCCESS;
    }
}
