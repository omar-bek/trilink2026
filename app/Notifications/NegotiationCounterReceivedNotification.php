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

class NegotiationCounterReceivedNotification extends Notification implements ShouldQueue
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
        [$bid, $msg] = $this->context();
        $amount = $msg ? number_format((float) ($msg->offer['amount'] ?? 0), 2) : '—';
        $currency = $msg->offer['currency'] ?? $bid?->currency ?? 'AED';
        $round = $msg?->round_number ?? '?';
        $expires = $msg?->expires_at?->format('M j, Y g:i A') ?? '—';

        return $this->baseMail($notifiable, 'notifications.negotiation.counter.subject', ['round' => $round])
            ->line($this->t($notifiable, 'notifications.negotiation.counter.line_round', ['round' => $round]))
            ->line($this->t($notifiable, 'notifications.negotiation.counter.line_amount', ['amount' => $amount, 'currency' => $currency]))
            ->line($this->t($notifiable, 'notifications.negotiation.counter.line_expiry', ['expires' => $expires]))
            ->action(
                $this->t($notifiable, 'notifications.negotiation.counter.action'),
                route('dashboard.bids.show', ['id' => $this->bidId]).'#negotiation'
            );
    }

    public function toArray(object $notifiable): array
    {
        [$bid, $msg] = $this->context();
        $amount = $msg ? number_format((float) ($msg->offer['amount'] ?? 0), 2) : '—';
        $currency = $msg->offer['currency'] ?? $bid?->currency ?? 'AED';

        return [
            'type' => 'action',
            'title' => $this->t($notifiable, 'notifications.negotiation.counter.title'),
            'message' => $this->t($notifiable, 'notifications.negotiation.counter.message', [
                'amount' => $amount,
                'currency' => $currency,
                'round' => $msg?->round_number ?? '?',
            ]),
            'entity_type' => 'bid',
            'entity_id' => $this->bidId,
            'negotiation_message_id' => $this->messageId,
        ];
    }

    /** @return array{0: ?Bid, 1: ?NegotiationMessage} */
    private function context(): array
    {
        return [
            Bid::find($this->bidId),
            NegotiationMessage::find($this->messageId),
        ];
    }
}
