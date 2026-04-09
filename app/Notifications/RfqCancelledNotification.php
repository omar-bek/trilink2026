<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to suppliers who submitted bids when the buyer cancels an RFQ
 * outright (different from CLOSED — cancellation means the buyer is
 * no longer pursuing the procurement at all). Carries the cancellation
 * reason so suppliers don't think the buyer ghosted them.
 */
class RfqCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Rfq $rfq,
        private readonly ?string $reason = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'rfq_matches',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->rfq->rfq_number;
        $title     = (string) $this->rfq->title;

        $mail = $this->baseMail($notifiable, 'notifications.rfq.cancelled.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.rfq.cancelled.line1', ['rfq' => $rfqNumber, 'title' => $title]));

        if ($this->reason) {
            $mail->line($this->t($notifiable, 'notifications.rfq.cancelled.line_reason', ['reason' => $this->reason]));
        }

        return $mail->action(
            $this->t($notifiable, 'notifications.common.action_view_rfq'),
            route('dashboard.rfqs.show', ['id' => $this->rfq->id])
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => $this->t($notifiable, 'notifications.rfq.cancelled.title'),
            'message'     => $this->t($notifiable, 'notifications.rfq.cancelled.message', ['rfq' => $this->rfq->rfq_number]),
            'entity_type' => 'rfq',
            'entity_id'   => $this->rfq->id,
        ];
    }
}
