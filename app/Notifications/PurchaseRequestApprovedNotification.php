<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PR author (buyer) when their request is approved. An RFQ is
 * typically auto-created in the same action, so the mail mentions that and
 * the CTA lands on the now-updated PR page.
 */
class PurchaseRequestApprovedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly PurchaseRequest $pr,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        // PR approval / rejection counts as a "bid update" preference since
        // it's the buyer's procurement workflow status — same toggle.
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'bid_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref = (string) $this->pr->title;

        return $this->baseMail($notifiable, 'notifications.purchase_request.approved.subject', ['ref' => $ref])
            ->line($this->t($notifiable, 'notifications.purchase_request.approved.message', ['ref' => $ref]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                route('dashboard.purchase-requests.show', ['id' => $this->pr->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'success',
            'title' => $this->t($notifiable, 'notifications.purchase_request.approved.title'),
            'message' => $this->t($notifiable, 'notifications.purchase_request.approved.message', [
                'ref' => (string) $this->pr->title,
            ]),
            'entity_type' => 'purchase_request',
            'entity_id' => $this->pr->id,
        ];
    }
}
