<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Models\NegotiationMessage;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NegotiationOfferAcceptedNotification extends Notification implements ShouldQueue
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
        $bid = Bid::find($this->bidId);
        $amount = $msg ? number_format((float) ($msg->offer['amount'] ?? 0), 2) : '—';
        $currency = $msg->offer['currency'] ?? $bid?->currency ?? 'AED';
        $signer = $msg?->signed_by_name ?? '—';

        return $this->baseMail($notifiable, 'notifications.negotiation.accepted.subject')
            ->line($this->t($notifiable, 'notifications.negotiation.accepted.line_intro', ['round' => $msg?->round_number ?? '?']))
            ->line($this->t($notifiable, 'notifications.negotiation.accepted.line_amount', ['amount' => $amount, 'currency' => $currency]))
            ->line($this->t($notifiable, 'notifications.negotiation.accepted.line_signer', ['signer' => $signer]))
            ->action(
                $this->t($notifiable, 'notifications.negotiation.accepted.action'),
                route('dashboard.bids.show', ['id' => $this->bidId])
            );
    }

    public function toArray(object $notifiable): array
    {
        $msg = NegotiationMessage::find($this->messageId);

        return [
            'type' => 'success',
            'title' => $this->t($notifiable, 'notifications.negotiation.accepted.title'),
            'message' => $this->t($notifiable, 'notifications.negotiation.accepted.message', [
                'round' => $msg?->round_number ?? '?',
            ]),
            'entity_type' => 'bid',
            'entity_id' => $this->bidId,
            'negotiation_message_id' => $this->messageId,
        ];
    }
}
