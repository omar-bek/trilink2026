<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every company manager in the buyer's company as soon as a buyer
 * creates a purchase request. The PR enters the manager's "Pending Approval"
 * inbox; the notification is the first signal they have new work waiting.
 */
class PurchaseRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly PurchaseRequest $pr,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'bid_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref = (string) $this->pr->title;

        return $this->baseMail($notifiable, 'notifications.purchase_request.submitted.subject')
            ->line($this->t($notifiable, 'notifications.purchase_request.submitted.message', ['ref' => $ref]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_review'),
                route('dashboard.purchase-requests.show', ['id' => $this->pr->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'info',
            'title'       => $this->t($notifiable, 'notifications.purchase_request.submitted.title'),
            'message'     => $this->t($notifiable, 'notifications.purchase_request.submitted.message', [
                'ref' => (string) $this->pr->title,
            ]),
            'entity_type' => 'purchase_request',
            'entity_id'   => $this->pr->id,
        ];
    }
}
