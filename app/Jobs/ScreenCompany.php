<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\SanctionsScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background job that runs a sanctions screening for a single company.
 *
 * Phase 2 / Sprint 7 / task 2.2 — replaces the synchronous
 * `$sanctions->screenCompany()` call that used to live inline inside
 * AuthService::registerCompany. Moving the call to a queue job means:
 *
 *   1. The provider's HTTP latency (sometimes 5-8s when OpenSanctions is
 *      under load) doesn't block the registration request — the manager
 *      sees the success page in <500ms.
 *
 *   2. A provider outage no longer 500s registration. The job retries
 *      with exponential backoff and lands a "review" verdict if every
 *      attempt fails, putting the company in the verification queue
 *      instead of leaving it permanently unscreened.
 *
 *   3. We can fan out daily re-screens (Sprint 2.4) by dispatching one
 *      ScreenCompany per company onto the same queue without DOSing the
 *      web workers.
 *
 * Triggered from:
 *   - `RegisterController::register` immediately after a new company is
 *     created (`$useCache = false` so the very first screening is fresh).
 *   - `App\Console\Commands\RescreenCompanies` daily at 04:00 UTC.
 *   - Admin "re-screen now" button via `AdminCompanyController::rescreenSanctions`
 *     (which still runs synchronously to give the admin instant feedback).
 */
class ScreenCompany implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $backoff = 30; // seconds; multiplied by attempt number by Laravel.

    public function __construct(
        public readonly int $companyId,
        public readonly ?int $triggeredBy = null,
        public readonly bool $useCache = true,
    ) {
        // Pin to a dedicated `sanctions` queue so the daily fan-out doesn't
        // contend with notifications / digests on the default worker. Set
        // via the trait helper rather than a `$queue` property to avoid
        // colliding with Queueable's nullable typed property declaration.
        $this->onQueue('sanctions');
    }

    public function handle(SanctionsScreeningService $service): void
    {
        $company = Company::find($this->companyId);
        if (! $company) {
            // Company was deleted between queueing and processing — drop
            // the job silently rather than failing it.
            return;
        }

        $service->screenCompany(
            company: $company,
            triggeredBy: $this->triggeredBy,
            useCache: $this->useCache,
        );
    }

    /**
     * The unique key prevents duplicate scheduling — re-dispatching the
     * same company while a job is already pending is a no-op. Avoids the
     * "smash retry" pattern where an admin clicks re-screen 5 times.
     */
    public function uniqueId(): string
    {
        return 'screen-company-'.$this->companyId;
    }
}
