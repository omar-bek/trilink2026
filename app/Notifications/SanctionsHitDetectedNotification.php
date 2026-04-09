<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\SanctionsScreening;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every active admin user the moment a sanctions screening returns
 * a HIT or REVIEW verdict for any company on the platform.
 *
 * The admin handles the alert by visiting the verification queue page,
 * inspecting the matched_entities snapshot stored on the screening row,
 * and either:
 *
 *   - Confirming the hit (company stays blocked, account suspended), or
 *   - Marking it a false positive (status flipped back to clean, allowing
 *     the company to transact again).
 *
 * Queued on the `notifications` queue so a slow mail driver doesn't
 * stall the screening pipeline.
 */
class SanctionsHitDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Company $company,
        private readonly SanctionsScreening $screening,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->baseMail($notifiable, 'notifications.sanctions.hit.subject', ['name' => $this->company->name])
            ->error()
            ->line($this->t($notifiable, 'notifications.sanctions.hit.line1'))
            ->line($this->t($notifiable, 'notifications.sanctions.hit.line_subject', ['name' => $this->company->name]))
            ->line($this->t($notifiable, 'notifications.sanctions.hit.line2'));

        try {
            $message = $message->action(
                $this->t($notifiable, 'notifications.common.action_review'),
                route('admin.verification.index'),
            );
        } catch (\Throwable) {
            // Verification queue route may not be registered yet during
            // partial deploys; skip the CTA in that case rather than crashing
            // the entire notification.
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'sanctions_hit',
            'title'        => $this->t($notifiable, 'notifications.sanctions.hit.title'),
            'message'      => $this->t($notifiable, 'notifications.sanctions.hit.message', ['name' => $this->company->name]),
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'screening_id' => $this->screening->id,
            'result'       => $this->screening->result,
            'match_count'  => $this->screening->match_count,
        ];
    }
}
