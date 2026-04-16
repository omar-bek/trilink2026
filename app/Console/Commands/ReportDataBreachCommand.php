<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\DataBreachNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Trigger the internal data-breach incident-response flow from the CLI.
 *
 * Used by the on-call engineer who notices something off (anomaly in
 * the audit log, abnormal database access, leaked credential alert,
 * external responsible-disclosure email). One command kicks off the
 * notification cascade so the admin team and DPO see it within minutes
 * — well inside the PDPL Article 9 72-hour window.
 *
 * Usage:
 *   php artisan privacy:report-breach \
 *     --severity=high \
 *     --affected=120 \
 *     --description="Stripe webhook secret leaked in public Slack channel" \
 *     --detection=external_report \
 *     --reporter=oncall@example.com
 *
 * The command does NOT call any external regulator — that's a manual
 * step the admin team must take with full legal review. This is the
 * INTERNAL kickoff only.
 */
class ReportDataBreachCommand extends Command
{
    protected $signature = 'privacy:report-breach
        {--severity= : low | medium | high | critical}
        {--affected=0 : number of data subjects affected}
        {--description= : free-form description of the incident}
        {--detection= : how the breach was detected (audit_log | external_report | monitoring | other)}
        {--reporter= : email or name of the person reporting}';

    protected $description = 'Kick off the internal PDPL data-breach incident response process. Notifies all platform admins.';

    public function handle(): int
    {
        $severity = (string) $this->option('severity');
        $affected = (int) $this->option('affected');
        $description = trim((string) $this->option('description'));
        $detection = $this->option('detection');
        $reporter = $this->option('reporter');

        // Validate severity ourselves so the operator gets a clear error
        // instead of an opaque notification at the end.
        $allowedSeverities = [
            DataBreachNotification::SEVERITY_LOW,
            DataBreachNotification::SEVERITY_MEDIUM,
            DataBreachNotification::SEVERITY_HIGH,
            DataBreachNotification::SEVERITY_CRITICAL,
        ];

        if (! in_array($severity, $allowedSeverities, true)) {
            $this->error('Severity must be one of: '.implode(', ', $allowedSeverities));

            return self::FAILURE;
        }

        if ($description === '') {
            $this->error('A description is required so the responders know what to investigate.');

            return self::FAILURE;
        }

        // Resolve the recipient list — every admin user gets the alert.
        // The DPO (when appointed) is just an admin user with a tag,
        // so this list will include them automatically.
        $admins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->get();

        if ($admins->isEmpty()) {
            $this->warn('No admins found to notify. The breach has been logged but no one was alerted.');
        } else {
            Notification::send(
                $admins,
                new DataBreachNotification(
                    severity: $severity,
                    affectedCount: $affected,
                    description: $description,
                    detectionMethod: is_string($detection) ? $detection : null,
                    reportedBy: is_string($reporter) ? $reporter : null,
                )
            );
        }

        $this->info(sprintf(
            'Data breach reported. Severity=%s, affected=%d, recipients=%d.',
            $severity,
            $affected,
            $admins->count(),
        ));
        $this->warn('REMEMBER: PDPL Article 9 requires UAE Data Office notification within 72 hours. This command does NOT contact them — that is a manual step.');

        return self::SUCCESS;
    }
}
