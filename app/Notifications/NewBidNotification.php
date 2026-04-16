<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBidNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Bid $bid,
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
        $rfqNumber = $this->bid->rfq?->rfq_number ?? '—';
        $amount = number_format((float) $this->bid->price, 2);
        $currency = $this->bid->currency ?? 'AED';

        return $this->baseMail($notifiable, 'notifications.bid.new.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.bid.new.line1'))
            ->line($this->t($notifiable, 'notifications.bid.new.line_rfq', ['rfq' => $rfqNumber]))
            ->line($this->t($notifiable, 'notifications.bid.new.line_amount', ['amount' => $amount, 'currency' => $currency]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_bid'),
                route('dashboard.bids.show', ['id' => $this->bid->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        $rfqNumber = $this->bid->rfq?->rfq_number ?? '—';
        $amount = number_format((float) $this->bid->price, 2);
        $currency = $this->bid->currency ?? 'AED';

        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.bid.new.title'),
            'message' => $this->t($notifiable, 'notifications.bid.new.message', [
                'amount' => $amount,
                'currency' => $currency,
                'rfq' => $rfqNumber,
            ]),
            'entity_type' => 'bid',
            'entity_id' => $this->bid->id,
            'rfq_id' => $this->bid->rfq_id,
        ];
    }
}
