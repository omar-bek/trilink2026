<?php

namespace App\Notifications;

use App\Models\PrivacyRequest;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment a PDPL Article 15 erasure request finishes
 * executing. The user has been anonymised by this point — but the
 * notification still goes out to the original email so they have
 * proof the platform acted on the request.
 */
class DataErasureCompletedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly PrivacyRequest $request,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail($notifiable, 'notifications.privacy.erasure_completed.subject')
            ->line($this->t($notifiable, 'notifications.privacy.erasure_completed.line1'))
            ->line($this->t($notifiable, 'notifications.privacy.erasure_completed.line2'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'success',
            'title' => $this->t($notifiable, 'notifications.privacy.erasure_completed.title'),
            'message' => $this->t($notifiable, 'notifications.privacy.erasure_completed.message'),
            'entity_type' => 'privacy_request',
            'entity_id' => $this->request->id,
        ];
    }
}
