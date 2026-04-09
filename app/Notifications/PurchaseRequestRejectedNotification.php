<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PR author (buyer) when their request is rejected by a manager.
 * Carries the rejection reason so the buyer can revise and resubmit.
 */
class PurchaseRequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly PurchaseRequest $pr,
        private readonly ?string $reason = null,
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

        $mail = $this->baseMail($notifiable, 'notifications.purchase_request.rejected.subject', ['ref' => $ref])
            ->line($this->t($notifiable, 'notifications.purchase_request.rejected.message', ['ref' => $ref]));

        if ($this->reason) {
            $mail->line($this->t($notifiable, 'notifications.purchase_request.rejected.line_reason', ['reason' => $this->reason]));
        }

        return $mail->action(
            $this->t($notifiable, 'notifications.common.action_view'),
            route('dashboard.purchase-requests.show', ['id' => $this->pr->id])
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => $this->t($notifiable, 'notifications.purchase_request.rejected.title'),
            'message'     => $this->t($notifiable, 'notifications.purchase_request.rejected.message', [
                'ref' => (string) $this->pr->title,
            ]),
            'entity_type' => 'purchase_request',
            'entity_id'   => $this->pr->id,
        ];
    }
}
