<?php

namespace App\Notifications;

use App\Models\PrivacyRequest;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a data subject when their PDPL Article 13 data export is
 * ready to download. The download link goes through the privacy
 * dashboard so the platform can enforce expiry + audit who pulled
 * the archive.
 */
class DataExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly PrivacyRequest $request,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        // Privacy notifications bypass user toggles deliberately —
        // PDPL gives data subjects the right to be informed about
        // their requests, so we MUST deliver this regardless of
        // their general preference settings.
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail($notifiable, 'notifications.privacy.export_ready.subject')
            ->line($this->t($notifiable, 'notifications.privacy.export_ready.line1'))
            ->line($this->t($notifiable, 'notifications.privacy.export_ready.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_download'),
                url('/dashboard/profile/privacy')
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'success',
            'title'       => $this->t($notifiable, 'notifications.privacy.export_ready.title'),
            'message'     => $this->t($notifiable, 'notifications.privacy.export_ready.message'),
            'entity_type' => 'privacy_request',
            'entity_id'   => $this->request->id,
            'action_url'  => '/dashboard/profile/privacy',
        ];
    }
}
