<?php

namespace App\Jobs;

use App\Models\PrivacyRequest;
use App\Services\Privacy\DataErasureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Background worker that executes a scheduled PDPL erasure request.
 *
 * Lifecycle:
 *
 *   1. User submits an erasure request → DataErasureService creates a
 *      PrivacyRequest with `scheduled_for = now + 30 days` and dispatches
 *      THIS job with `delay($30days)`.
 *   2. The 30-day cooling period elapses. Queue worker picks the job up.
 *   3. Job re-checks the request status (the user may have withdrawn it
 *      via the privacy dashboard) and exits cleanly if it's no longer
 *      `pending` or `approved`.
 *   4. If still actionable, calls DataErasureService::executeErasure
 *      which anonymises the user atomically.
 *
 * Idempotency:
 *
 *   - ShouldBeUnique on `privacy_request_id`. Multiple dispatches (e.g.
 *     admin clicked "Run now" while the scheduled job is also queued)
 *     collapse to a single execution.
 *   - executeErasure() inside the service is itself a no-op for
 *     already-completed requests.
 */
class ExecutePrivacyErasureJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $privacyRequestId,
    ) {
        $this->onQueue('privacy');
    }

    public function uniqueId(): string
    {
        return 'privacy-erasure:' . $this->privacyRequestId;
    }

    public function handle(DataErasureService $service): void
    {
        $request = PrivacyRequest::find($this->privacyRequestId);

        if (!$request) {
            // Request was hard-deleted (shouldn't happen — privacy_requests
            // are append-only — but defensive). Drop the job silently.
            return;
        }

        $service->executeErasure($request);
    }

    public function failed(Throwable $exception): void
    {
        // Hook for ops alerting. For Phase 2 the failed_jobs table is
        // the source of truth — Phase 8 wires up admin notifications
        // for failed privacy jobs because the legal exposure of a
        // missed erasure is high.
        report($exception);
    }
}
