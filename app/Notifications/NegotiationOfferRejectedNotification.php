<?php

namespace App\Notifications;

use App\Models\NegotiationMessage;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NegotiationOfferRejectedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly int $bidId,
        private readonly int $messageId,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'bid_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = NegotiationMessage::find($this->messageId);

        return $this->baseMail($notifiable, 'notifications.negotiation.rejected.subject')
            ->line($this->t($notifiable, 'notifications.negotiation.rejected.line_intro', ['round' => $msg?->round_number ?? '?']))
            ->action(
                $this->t($notifiable, 'notifications.negotiation.rejected.action'),
                route('dashboard.bids.show', ['id' => $this->bidId])
            );
    }

    public function toArray(object $notifiable): array
    {
        $msg = NegotiationMessage::find($this->messageId);

        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.negotiation.rejected.title'),
            'message' => $this->t($notifiable, 'notifications.negotiation.rejected.message', [
                'round' => $msg?->round_number ?? '?',
            ]),
            'entity_type' => 'bid',
            'entity_id' => $this->bidId,
            'negotiation_message_id' => $this->messageId,
        ];
    }
}
