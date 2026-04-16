<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Targeted variant of RfqPublishedNotification — sent when the buyer
 * personally invites a specific supplier company instead of relying on
 * the broadcast match. Used by closed/private RFQs and pre-qualified
 * supplier lists.
 */
class RfqInvitationNotification extends Notification implements ShouldQueue
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
        // Invitations bypass the rfq_matches toggle deliberately — being
        // hand-picked by a buyer is a higher-signal event than a category
        // broadcast and skipping it would frustrate the supplier.
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->rfq->rfq_number;

        return $this->baseMail($notifiable, 'notifications.rfq.invitation.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.rfq.invitation.line1'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs.show', ['id' => $this->rfq->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.rfq.invitation.title'),
            'message' => $this->t($notifiable, 'notifications.rfq.invitation.message', ['rfq' => $this->rfq->rfq_number]),
            'entity_type' => 'rfq',
            'entity_id' => $this->rfq->id,
        ];
    }
}
