<?php

namespace App\Notifications;

use App\Models\PrivacyRequest;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a PDPL Article 15 erasure request is rejected because
 * one of the carve-outs in 15(2) applies (active contracts, open
 * disputes, retention obligations). The reason MUST be communicated
 * — refusing without explanation is itself a PDPL violation.
 */
class DataErasureDeniedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly PrivacyRequest $request,
        private readonly string $reason,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail($notifiable, 'notifications.privacy.erasure_denied.subject')
            ->error()
            ->line($this->t($notifiable, 'notifications.privacy.erasure_denied.line1'))
            ->line($this->t($notifiable, 'notifications.privacy.erasure_denied.line_reason', ['reason' => $this->reason]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                url('/dashboard/profile/privacy')
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.privacy.erasure_denied.title'),
            'message' => $this->t($notifiable, 'notifications.privacy.erasure_denied.message'),
            'entity_type' => 'privacy_request',
            'entity_id' => $this->request->id,
        ];
    }
}
