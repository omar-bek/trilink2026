<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\SuspectedCollusionNotification;
use App\Services\Procurement\AntiCollusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Phase 7 (UAE Compliance Roadmap) — runs the anti-collusion
 * detection suite against an RFQ once it moves out of the OPEN
 * state (awarded / closed / cancelled). Dispatched by the
 * BidController accept flow and by any future "close RFQ" endpoint.
 *
 * Findings are stored in a dedicated `collusion_alerts` table so
 * the admin can triage them asynchronously. Critical-severity
 * findings also fire a SuspectedCollusionNotification to every
 * platform admin.
 *
 * Queue: 'compliance' — separate from the transactional queues so
 * a slow pattern-matching pass doesn't block invoice issuance or
 * sanctions screening.
 */
class AnalyzeRfqForCollusionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 120;

    public function __construct(
        public readonly int $rfqId,
    ) {
        $this->onQueue('compliance');
    }

    public function uniqueId(): string
    {
        return 'collusion-check:' . $this->rfqId;
    }

    public function handle(AntiCollusionService $service): void
    {
        $rfq = Rfq::find($this->rfqId);
        if (!$rfq) {
            return;
        }

        $findings = $service->analyzeRfq($rfq);
        if ($findings->isEmpty()) {
            return;
        }

        // Persist each finding as a collusion_alert row (if the table
        // exists — Phase 7 migration adds it). The admin queue reads
        // these rows and surfaces them in the Anti-Collusion Alerts
        // tab. We wrap in try/catch because the table may not exist
        // in early deployments running the job before the migration.
        foreach ($findings as $finding) {
            try {
                DB::table('collusion_alerts')->insert([
                    'rfq_id'     => $rfq->id,
                    'type'       => $finding['type'],
                    'severity'   => $finding['severity'],
                    'evidence'   => json_encode($finding['evidence'], JSON_UNESCAPED_UNICODE),
                    'status'     => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable) {
                // Table doesn't exist yet — log to audit instead.
                \App\Models\AuditLog::create([
                    'user_id'       => null,
                    'company_id'    => $rfq->company_id,
                    'action'        => \App\Enums\AuditAction::CREATE,
                    'resource_type' => 'CollusionAlert',
                    'resource_id'   => $rfq->id,
                    'before'        => null,
                    'after'         => $finding,
                    'ip_address'    => '0.0.0.0',
                    'user_agent'    => 'AnalyzeRfqForCollusionJob',
                    'status'        => $finding['severity'],
                ]);
            }
        }

        // Notify admins for critical findings.
        $criticals = $findings->where('severity', 'critical');
        if ($criticals->isNotEmpty()) {
            $admins = User::where('role', UserRole::ADMIN->value)->get();
            if ($admins->isNotEmpty()) {
                Notification::send(
                    $admins,
                    new SuspectedCollusionNotification($rfq, $criticals->all())
                );
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
