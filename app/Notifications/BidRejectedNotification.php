<?php

namespace App\Notifications;

use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the bid's submitter (supplier) when their bid is rejected.
 * Rejection happens either directly or indirectly when another bid on the
 * same RFQ is accepted (the accept() flow auto-rejects all siblings).
 */
class BidRejectedNotification extends Notification implements ShouldQueue
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
            ->subject("Bid update — {$this->bid->rfq?->title}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your bid on RFQ #{$this->bid->rfq?->rfq_number} was not selected by the buyer.")
            ->line("We'll notify you when new RFQs matching your categories are posted.")
            ->action('Browse Available RFQs', route('dashboard.rfqs'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => 'Bid Not Selected',
            'message'     => "Your bid on #{$this->bid->rfq?->rfq_number} was not selected",
            'entity_type' => 'bid',
            'entity_id'   => $this->bid->id,
        ];
    }
}
