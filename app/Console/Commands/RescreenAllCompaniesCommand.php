<?php

namespace App\Console\Commands;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Services\SanctionsScreeningService;
use Illuminate\Console\Command;

/**
 * Phase 9 (UAE Compliance Roadmap) — weekly sanctions re-screening.
 *
 * Sanctions lists (UN, OFAC, EU, UAE Local Terrorist List) update
 * frequently. The platform screens each company ONCE at registration
 * (Phase 0) but never re-checks. A company sanctioned AFTER
 * registration would continue operating on the platform undetected.
 *
 * This command walks every active company and re-runs the sanctions
 * screening pipeline. Any new hit:
 *   - Demotes the company's verification level to UNVERIFIED
 *   - Stamps sanctions_status = 'hit'
 *   - The SanctionsScreeningService already sends admin notifications
 *     on hits (Phase 0 behaviour), so no extra notification logic
 *     is needed here.
 *
 * Schedule: weekly (Sunday 02:00 GST recommended — low traffic).
 *
 * Usage:
 *   php artisan sanctions:rescreen-all
 *   php artisan sanctions:rescreen-all --dry-run
 *   php artisan sanctions:rescreen-all --chunk=100
 */
class RescreenAllCompaniesCommand extends Command
{
    protected $signature = 'sanctions:rescreen-all
        {--dry-run : Show what would be screened without calling the provider}
        {--chunk=200 : Companies per batch}';

    protected $description = 'Re-screen all active companies against current sanctions lists.';

    public function handle(SanctionsScreeningService $service): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $chunk = (int) ($this->option('chunk') ?: 200);

        $query = Company::query()
            ->where('status', CompanyStatus::ACTIVE->value)
            ->orderBy('id');

        $total = $query->count();
        $screened = 0;
        $hits = 0;
        $errors = 0;

        if ($total === 0) {
            $this->info('No active companies to re-screen.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d active companies...',
            $isDryRun ? '[DRY RUN] Would screen' : 'Re-screening',
            $total
        ));

        $query->chunk($chunk, function ($companies) use ($service, $isDryRun, &$screened, &$hits, &$errors) {
            foreach ($companies as $company) {
                $screened++;

                if ($isDryRun) {
                    $this->line("  [dry-run] #{$company->id} {$company->name}");
                    continue;
                }

                try {
                    // screenCompany() returns a SanctionsScreening
                    // model row — the verdict lives in ->result. The
                    // service already calls applyVerdict() which
                    // demotes + notifies admins on hits, so we only
                    // need to count here.
                    $screening = $service->screenCompany($company, triggeredBy: null, useCache: false);
                    $verdict = $screening->result ?? 'clean';

                    if (in_array($verdict, ['hit', 'review'], true)) {
                        $hits++;
                        $this->warn("  HIT: #{$company->id} {$company->name} — {$verdict}");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("  ERROR: #{$company->id} — {$e->getMessage()}");
                }
            }
        });

        $this->info(sprintf(
            'Re-screening complete: %d screened, %d hits, %d errors.',
            $screened, $hits, $errors
        ));

        if ($hits > 0) {
            $this->warn('Review the hits in the admin verification queue immediately.');
        }

        return $hits > 0 || $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
