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

/**
 * Sent to the bid's submitter (supplier) when their bid is rejected.
 * Rejection happens either directly or indirectly when another bid on the
 * same RFQ is accepted (the accept() flow auto-rejects all siblings).
 */
class BidRejectedNotification extends Notification implements ShouldQueue
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

        return $this->baseMail($notifiable, 'notifications.bid.rejected.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.bid.rejected.line1', ['rfq' => $rfqNumber]))
            ->line($this->t($notifiable, 'notifications.bid.rejected.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs')
            );
    }

    public function toArray(object $notifiable): array
    {
        $rfqNumber = $this->bid->rfq?->rfq_number ?? '—';

        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.bid.rejected.title'),
            'message' => $this->t($notifiable, 'notifications.bid.rejected.message', ['rfq' => $rfqNumber]),
            'entity_type' => 'bid',
            'entity_id' => $this->bid->id,
        ];
    }
}
