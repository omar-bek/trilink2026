<?php

namespace App\Notifications;

use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Bid $bid,
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
        return (new MailMessage)
            ->subject('New Bid Received')
            ->line("A new bid has been submitted for RFQ #{$this->bid->rfq->rfq_number}")
            ->line("Amount: {$this->bid->price} {$this->bid->currency}")
            ->action('View Bid', config('app.frontend_url') . "/bids/{$this->bid->id}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => 'New Bid Received',
            'message' => "A new bid of {$this->bid->price} {$this->bid->currency} has been submitted",
            'entity_type' => 'bid',
            'entity_id' => $this->bid->id,
            'rfq_id' => $this->bid->rfq_id,
        ];
    }
}
