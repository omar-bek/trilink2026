<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to all suppliers that submitted at least one bid on an RFQ
 * the moment the RFQ moves to CLOSED. Differs from RfqAwarded — this
 * is the "no more bids accepted" event, NOT the award decision. The
 * award decision goes through BidAcceptedNotification + LosingBidNotice.
 */
class RfqClosedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Rfq $rfq,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'rfq_matches',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->rfq->rfq_number;
        $title = (string) $this->rfq->title;

        return $this->baseMail($notifiable, 'notifications.rfq.closed.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.rfq.closed.line1', ['rfq' => $rfqNumber, 'title' => $title]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs.show', ['id' => $this->rfq->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.rfq.closed.title'),
            'message' => $this->t($notifiable, 'notifications.rfq.closed.message', ['rfq' => $this->rfq->rfq_number]),
            'entity_type' => 'rfq',
            'entity_id' => $this->rfq->id,
        ];
    }
}
