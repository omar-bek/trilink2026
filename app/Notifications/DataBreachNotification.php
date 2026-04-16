<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when a data breach is reported via `php artisan privacy:report-breach`.
 *
 * Federal Decree-Law 45/2021 Article 9 requires the controller to notify
 * the UAE Data Office within 72 hours of becoming aware of a personal-data
 * breach, and to notify affected data subjects "without undue delay" if
 * the breach is likely to cause high risk to their rights.
 *
 * This notification is the INTERNAL escalation — it goes to platform
 * admins (and the DPO if appointed) so they can:
 *
 *   1. Convene the incident response process
 *   2. Decide whether the threshold for Data Office notification is met
 *   3. Decide whether the threshold for data subject notification is met
 *   4. Trigger the actual external notifications (which are out of scope
 *      for Phase 2 — they're a manual workflow today)
 *
 * The notification carries enough metadata for the admin to triage
 * without having to dig into the database: severity, affected count,
 * detection method, and a free-form description of the incident.
 */
class DataBreachNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public readonly string $severity,
        public readonly int $affectedCount,
        public readonly string $description,
        public readonly ?string $detectionMethod = null,
        public readonly ?string $reportedBy = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = now()->addHours(72);

        return $this->baseMail($notifiable, 'notifications.privacy.breach.subject')
            ->line($this->t($notifiable, 'notifications.privacy.breach.message'))
            ->line(strtoupper($this->severity).' — '.$this->affectedCount.' subjects')
            ->line($this->description)
            ->line('72h deadline: '.$deadline->toDayDateTimeString().' GST')
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                url('/dashboard/admin/audit')
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'data_breach',
            'title' => $this->t($notifiable, 'notifications.privacy.breach.title').' ('.strtoupper($this->severity).')',
            'message' => "{$this->affectedCount} — ".$this->truncate($this->description, 140),
            'severity' => $this->severity,
            'affected_count' => $this->affectedCount,
            'detection_method' => $this->detectionMethod,
            'reported_by' => $this->reportedBy,
            'pdpl_deadline' => now()->addHours(72)->toIso8601String(),
            'action_url' => '/dashboard/admin/audit',
        ];
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
